# 宝莲台 V10.1 技术指导文档（TECH_GUIDE）

**版本**: v10.1  
**日期**: 2025-12-16  
**适用项目**: BinaryEcom20（Laravel）  
**依赖需求文档**:
- `docs/宝莲台·全生态福慧共生-用户需求文档（V10.1）.md`
- `docs/宝莲台·全生态福慧共生-结算与对账需求（V10.1）.md`
- `docs/宝莲台·全生态福慧共生-数据字典与字段表（V10.1）.md`
- `docs/宝莲台 · 财务风控与 K 值算法详解.md`

---

## 1. 技术架构概览

### 1.1 核心分层
```
用户端（Web/H5/App）
    ↓
API Layer（Laravel Controller）
    ↓
Service Layer（业务逻辑/事务编排）
    ├── BonusService（直推/层碰/对碰/管理）
    ├── SettlementService（周结/季度结算）
    ├── PVLedgerService（PV台账与冲正）
    ├── PointsService（莲子积分）
    └── AdjustmentService（退款/纠错批次）
    ↓
Repository/DAO（数据访问/幂等性保证）
    ↓
Database（MySQL/PostgreSQL）
```

### 1.2 关键技术约束
- **事务边界**: 订单发货 PV 入账、直推/层碰实时发放必须在同一事务内完成（或使用可靠消息/补偿机制）。
- **幂等性**: 所有涉及资金/PV/积分的操作必须支持幂等（通过 `source_type` + `source_id` + `user_id` 等唯一键实现）。
- **批次串联**: 周结算/季度结算需要 `week_key`/`quarter_key` 作为主键，与 `orders.trx`、`adjustment_batches.batch_key` 等统一串联追溯。
- **可复算**: 周结算/季度结算需要支持"预演模式"（Dry Run），不写入流水，仅返回计算结果。
- **负余额支持**: 钱包余额 `users.balance` 允许为负（退款冲正场景），并需业务层限制负余额用户的提现/转账。

---

## 2. 核心数据模型与表设计

### 2.1 PV 台账（pv_ledger）
**用途**: 替代旧 `bv_logs`，作为 V10.1 主台账，支持按订单回溯与冲正。

**关键字段**:
- `source_type`: order / weekly_settlement / adjustment
- `source_id`: orders.trx / week_key / batch_key
- `trx_type`: + / -
- `adjustment_batch_id`: 调整批次 ID（用于 Finalize 后退款）
- `reversal_of_id`: 被冲正的原始 pv_ledger.id

**幂等键**（强烈建议）:
```sql
UNIQUE(source_type, source_id, user_id, position, trx_type)
```

**索引建议**:
```sql
INDEX(user_id, created_at)
INDEX(from_user_id, created_at)
INDEX(source_type, source_id)
INDEX(adjustment_batch_id)
INDEX(reversal_of_id)
```

### 2.2 周结算主表（weekly_settlements）
**字段摘要**:
- `week_key`: 2025-W50（唯一）
- `start_date` / `end_date`: 自然周起止
- `total_pv`: 本周新增 PV 总计
- `fixed_sales`: 直推+层碰（已发+应计预留）
- `global_reserve`: 功德池 4%
- `variable_potential`: 对碰封顶后理论 + 管理理论（未乘 K）
- `k_factor`: 计算得出的 K 值
- `finalized_at`: 执行时间
- `config_snapshot`: JSON 快照（比例/封顶/周定义等参数）

### 2.3 待处理奖金（pending_bonuses）
**用途**: 收款人未激活时，奖金不入余额，先记入待处理。

**关键字段**:
- `bonus_type`: direct / level_pair / pair / matching
- `recipient_id`: 收款人
- `source_type`: order / weekly_settlement
- `source_id`: orders.trx / week_key
- `accrued_week_key`: 应计预留的周（用于 FixedSales 统计）
- `status`: pending / released / rejected
- `release_mode`: auto / manual
- `released_trx`: transactions.id（释放后的流水 ID）

### 2.4 调整批次（adjustment_batches + adjustment_entries）
**用途**: 处理 Finalize 后退款/纠错，产生可追溯的负向流水。

**adjustment_batches**:
- `batch_key`: 唯一批次号（如 ADJ-20250116-001）
- `reason_type`: refund_after_finalize / manual_correction
- `reference_type`: order / weekly_settlement
- `reference_id`: 被调整的来源
- `finalized_by`: 审核人
- `finalized_at`: 确认时间
- `snapshot`: JSON 快照（调整详情）

**adjustment_entries**:
- `batch_id`: 关联 adjustment_batches.id
- `asset_type`: wallet / pv / points
- `user_id`: 被调整用户
- `amount`: 调整量（可为负）
- `reversal_of_id`: 被冲正的原始流水 ID

---

## 3. 核心算法实现

### 3.1 订单发货 PV 入账（事件触发）

**触发时机**: 订单状态从 `pending` → `shipped`。

**事务边界**（必须原子性完成以下所有动作）:

```php
DB::transaction(function () use ($order) {
    // 1. PV 台账：沿安置链向上累加左右区 PV
    $pvEntries = PVLedgerService::creditPVFromOrder($order);
    
    // 2. 直推奖：实时发放或进入待处理
    $directBonus = BonusService::issueDirectBonus($order);
    
    // 3. 层碰奖：回溯点亮并发放或待处理
    $levelPairBonuses = BonusService::triggerLevelPairBonus($order);
    
    // 4. 莲子积分 A/B：自购+直推
    $pointsA = PointsService::creditSelfPurchase($order->user_id, $order->quantity);
    $pointsB = PointsService::creditDirectReferral($order->ref_by, $order->quantity);
    
    // 5. 活跃标记：更新 last_activity_date
    User::where('id', $order->user_id)->update(['last_activity_date' => now()]);
    
    // 6. 订单状态更新
    $order->status = 'shipped';
    $order->save();
});
```

**幂等性保证**:
- `pv_ledger` 的 UNIQUE 键阻止重复入账。
- `pending_bonuses` / `transactions` 通过 `source_type=order` + `source_id=orders.trx` + `user_id` 防重。
- `user_points_log` 通过 `(source_type=PURCHASE, reference_id=orders.trx, user_id)` 唯一键防重。

### 3.2 周结算算法（Cron/手动触发）

**输入**:
- `week_key`: 如 2025-W50
- `start_date`, `end_date`: 周起止时间

**计算步骤**（参考 K 值算法详解）:

**Step 1: 统计本周刚性支出（FixedSales）**
```php
$fixedSales = DB::table('transactions')
    ->whereBetween('created_at', [$start, $end])
    ->whereIn('remark', ['direct_bonus', 'level_pair_bonus'])
    ->sum('amount');

// + 应计预留（待处理奖金的应计）
$accruedPending = DB::table('pending_bonuses')
    ->where('accrued_week_key', $week_key)
    ->whereIn('bonus_type', ['direct', 'level_pair'])
    ->sum('amount');

$fixedSales += $accruedPending;
```

**Step 2: 统计本周总 PV**
```php
$totalPV = DB::table('pv_ledger')
    ->where('source_type', 'order')
    ->whereBetween('created_at', [$start, $end])
    ->where('trx_type', '+')
    ->sum('amount');
```

**Step 3: 功德池预留**
```php
$globalReserve = $totalPV * 0.04; // 4% = 护持池1% + 领航池3%
```

**Step 4: 计算每用户对碰/管理理论（应用周封顶）**
```php
foreach ($users as $user) {
    // 对碰计算使用“当前累计余额”（Balance PV）
    $weakPV = min($user->bv_left, $user->bv_right);
    $pairCount = floor($weakPV / 3000);
    $pairPotential = $pairCount * 300;
    
    // 统计“本周新增 PV”（New PV），用于 TEAM 莲子计算（需单独统计，不能混淆）
    $user->weak_pv_new = PVLedgerService::getWeeklyNewWeakPV($user->id, $week_key);
    
    // 周封顶（仅对碰）
    $capAmount = $user->getWeeklyCapAmount(); // 根据 rank_level
    $pairCapped = min($pairPotential, $capAmount);
    
    // 管理奖（基于下级对碰实发或理论）
    $matchingPotential = MatchingService::calculateMatchingBonus($user, $week_key);
    
    $userSummaries[] = [
        'user_id' => $user->id,
        'pair_capped_potential' => $pairCapped,
        'matching_potential' => $matchingPotential,
    ];
}

$variablePotential = array_sum(array_column($userSummaries, 'pair_capped_potential'))
                    + array_sum(array_column($userSummaries, 'matching_potential'));
```

**Step 5: 计算 K 值**
```php
$totalCap = $totalPV * 0.7;
$remaining = $totalCap - $globalReserve - $fixedSales;

if ($remaining >= $variablePotential) {
    $kFactor = 1.0;
} elseif ($remaining > 0) {
    $kFactor = $remaining / $variablePotential;
} else {
    $kFactor = 0;
}
```

**Step 6: 发放与落库**
```php
DB::transaction(function () use ($week_key, $userSummaries, $kFactor) {
    // 写入周结算主表
    WeeklySettlement::create([
        'week_key' => $week_key,
        'total_pv' => $totalPV,
        'fixed_sales' => $fixedSales,
        'global_reserve' => $globalReserve,
        'variable_potential' => $variablePotential,
        'k_factor' => $kFactor,
        'config_snapshot' => config('bonus.v10_1'),
        'finalized_at' => now(),
    ]);
    
    // 逐用户发放对碰/管理
    foreach ($userSummaries as $summary) {
        $pairPaid = $summary['pair_capped_potential'] * $kFactor;
        $matchingPaid = $summary['matching_potential'] * $kFactor;
        
        // 对碰入账
        Transaction::create([
            'user_id' => $summary['user_id'],
            'trx_type' => '+',
            'amount' => $pairPaid,
            'remark' => 'pair_bonus',
            'details' => json_encode(['week_key' => $week_key, 'k_factor' => $kFactor]),
        ]);
        
        // 管理入账
        Transaction::create([
            'user_id' => $summary['user_id'],
            'trx_type' => '+',
            'amount' => $matchingPaid,
            'remark' => 'matching_bonus',
            'details' => json_encode(['week_key' => $week_key, 'k_factor' => $kFactor]),
        ]);
        
        // PV 扣减（仅扣减已发放对碰的 PV）
        $deductPV = floor($pairPaid / 300) * 3000;
        PVLedgerService::deductPVForWeeklySettlement($summary['user_id'], $deductPV, $week_key);
        
        // 莲子 TEAM（C）：按弱区新增 PV * 0.1（注意使用 weak_pv_new 而非余额）
        $teamPoints = $summary['weak_pv_new'] * 0.1;
        PointsService::creditTeamPoints($summary['user_id'], $teamPoints, $week_key);
        
        // 写入用户周结汇总
        WeeklySettlementUserSummary::create([...]);
    }
});
```

### 3.3 季度分红算法

**1% 消费商池（护持池）**:
```php
$poolStockist = $quarterTotalPV * 0.01;

$eligibleUsers = User::where('personal_purchase_count', '>=', 3)
    ->where(function($query) use ($quarter) {
        // 活跃条件：当季自购 1 单 OR 当季直推 1 单
        $query->whereHas('ordersInQuarter', function($q) use ($quarter) {
            $q->where('status', 'shipped')
              ->whereBetween('created_at', [$quarter->start, $quarter->end]);
        })
        ->orWhereHas('directReferralsInQuarter', function($q) use ($quarter) {
            $q->where('activation_date', '>=', $quarter->start)
              ->where('activation_date', '<=', $quarter->end);
        });
    })
    ->get();

$totalShares = $eligibleUsers->sum('personal_purchase_count');
$unitValue = $poolStockist / $totalShares;

foreach ($eligibleUsers as $user) {
    $dividend = $user->personal_purchase_count * $unitValue;
    // 入账 + dividend_logs
}
```

**3% 领导人池（领航池）**:
```php
$poolLeader = $quarterTotalPV * 0.03;

$eligibleLeaders = User::where('quarterly_weak_pv', '>=', 10000)
    ->whereNotNull('leader_rank_code')
    ->get();

$totalScore = $eligibleLeaders->sum(function ($user) {
    return $user->quarterly_weak_pv * $user->leader_rank_multiplier;
});

$unitValue = $poolLeader / $totalScore;

foreach ($eligibleLeaders as $leader) {
    $score = $leader->quarterly_weak_pv * $leader->leader_rank_multiplier;
    $dividend = $score * $unitValue;
    // 入账 + dividend_logs
}
```

---

## 4. 退款与冲正机制

### 4.1 发货前取消
- 订单状态设为 `canceled`。
- 不产生 PV/奖金/积分，无需冲正。

### 4.2 发货后退款（Finalize 前）
- 自动创建 `adjustment_batches`（`reason_type=refund_before_finalize`）。
- 生成负向流水：
  - `pv_ledger`: `-` 与原订单对应的 PV，并填写 `reversal_of_id`。
  - `transactions`: `-` 直推/层碰奖金（若已发），填写 `adjustment_batch_id`。
  - `user_points_log`: `-` A/B 积分，填写 `reversal_of_id`。
- 允许余额为负。

### 4.3 发货后退款（Finalize 后）
- 进入人工审核流程。
- 创建 `adjustment_batches`（`reason_type=refund_after_finalize`）。
- 审核确认后 `finalized_at` 填写，并批量生成负向流水。
- 写入 `audit_logs`（`action_type=adjustment_finalize`）。

---

## 5. 幂等性与并发控制

### 5.1 核心幂等键设计

| 表名 | 幂等键组合 |
|---|---|
| `pv_ledger` | `(source_type, source_id, user_id, position, trx_type)` |
| `transactions` | `(user_id, remark, source_type, source_id)` |
| `user_points_log` | `(user_id, source_type, reference_id)` |
| `pending_bonuses` | `(recipient_id, bonus_type, source_type, source_id)` |
| `weekly_settlements` | `(week_key)` |
| `quarterly_settlements` | `(quarter_key)` |

### 5.2 并发控制
- **周结算**: 使用分布式锁（Redis）或数据库行锁 `SELECT ... FOR UPDATE`，确保同一 `week_key` 不被并发执行。
- **订单 PV 入账**: 使用数据库事务 + 唯一键约束自然阻止重复。

---

## 6. 对账与追溯

### 6.1 追溯链示例
- 某用户查看某笔交易流水（`transactions.id=12345`）：
  - `remark=pair_bonus` → `details` 含 `week_key=2025-W50` → 查 `weekly_settlements`。
  - `source_type=order` → `source_id=orders.trx` → 查 `orders` 与 `pv_ledger`。

### 6.2 可复算（预演模式）
- 周结算/季度结算提供 `dryRun=true` 参数。
- 执行全部计算逻辑，但不写入 `transactions` / `pv_ledger` / `dividend_logs`。
- 返回 JSON 结果供财务预演与校验。

---

## 7. 测试与验收

### 7.1 单元测试（PHPUnit）
- **K 值计算**: 构造 `Remaining < A`，验证 K 值折算正确。
- **周封顶**: 构造用户对碰理论 > 封顶，验证封顶后参与 K（管理不计入封顶）。
- **幂等性**: 同一订单重复触发 PV 入账，验证仅入账一次。
- **负余额**: 构造冲正后余额为负，验证提现被限制。

### 7.2 集成测试
- 完整流程：下单 → 发货 → PV 入账 → 直推/层碰实时发放 → 周结算 → 季度分红 → 退款冲正。
- 验证所有流水可追溯、批次可对账。

---

## 8. 关键技术决策记录

| 决策点 | 选择方案 | 理由 |
|---|---|---|
| PV 台账 | 新建 `pv_ledger` | 支持冲正与 `reversal_of_id`，比 `bv_logs` 更结构化。 |
| 待处理奖金 | `pending_bonuses` 表 | 独立台账便于应计预留统计与释放审计。 |
| 周封顶 | 仅对碰 | 管理奖不计入封顶，但参与 K 值折算。 |
| 季度分红未达标 | 当季不参与分配，池留存 | 不做"后续释放"功能，降低复杂度。 |
| 莲子 TEAM 产出 | 随周结批次一次性产出 | 避免实时计算团队算力的性能问题。 |
| 退款冲正（Finalize 后） | 调整批次 + 人工审核 | 避免回滚重算，保证历史批次不可变。 |

---

## 9. 性能与扩展性

### 9.1 性能瓶颈点
- **层碰回溯**: 深度树回溯可能慢 → 限制最大回溯层级或异步队列处理。
- **周结算**: 全用户遍历计算 → 考虑分片/分批执行。

### 9.2 扩展建议
- **读写分离**: 报表查询走从库，结算写主库。
- **缓存**: 用户等级/职级/左右区 PV 等高频读数据可缓存（Redis），失效策略为"写时失效"。

---

## 10. 附录：关键 SQL 示例

### 10.1 创建 pv_ledger 表
```sql
CREATE TABLE pv_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    from_user_id BIGINT UNSIGNED NULL,
    position TINYINT NOT NULL COMMENT '1=Left, 2=Right',
    level INT NOT NULL,
    amount DECIMAL(16,8) NOT NULL,
    trx_type VARCHAR(2) NOT NULL COMMENT '+ or -',
    source_type VARCHAR(30) NOT NULL COMMENT 'order/weekly_settlement/adjustment',
    source_id VARCHAR(50) NOT NULL,
    adjustment_batch_id BIGINT NULL,
    reversal_of_id BIGINT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id, created_at),
    INDEX(from_user_id, created_at),
    INDEX(source_type, source_id),
    INDEX(adjustment_batch_id),
    INDEX(reversal_of_id),
    UNIQUE(source_type, source_id, user_id, position, trx_type)
);
```

---

**结束语**：本技术指导文档为 V10.1 需求的落地提供最小闭环技术方案，所有口径与数据字典、结算需求、K 值算法详解保持一致。研发团队应严格遵循本文档中的幂等性设计、事务边界、批次串联等约束，确保系统长期可对账、可追溯、可扩展。