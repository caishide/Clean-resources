# 宝莲台 V10.1 开发任务计划（TASK_PLAN）

**版本**: v10.1  
**日期**: 2025-12-16  
**项目**: BinaryEcom20（Laravel）  
**周期**: 预计 6-8 周（根据团队资源调整）  
**技术依据**: @TECH_GUIDE_v10.1.md

---

## 1. 里程碑与阶段划分

### 第一阶段：数据库与基础建模（Week 1-2）
**目标**: 完成 V10.1 新表结构落库、索引优化、幂等键设计。

**任务清单**:
- [ ] **PV 台账（pv_ledger）**: 创建表、索引、幂等键 `UNIQUE(source_type, source_id, user_id, position, trx_type)`。
- [ ] **待处理奖金（pending_bonuses）**: 创建表、索引、幂等键 `UNIQUE(recipient_id, bonus_type, source_type, source_id)`。
- [ ] **周结算表**（weekly_settlements + weekly_settlement_user_summaries）: 创建主表与用户汇总表。
- [ ] **季度结算表**（quarterly_settlements + dividend_logs）: 创建主表与分红明细表。
- [ ] **调整批次**（adjustment_batches + adjustment_entries）: 创建表、关联 pv_ledger/transactions/user_points_log 的 `adjustment_batch_id` 字段。
- [ ] **莲子积分**（user_assets + user_points_log）: 创建表、索引、幂等键 `UNIQUE(user_id, source_type, reference_id)`。
- [ ] **层碰记录**（user_level_hits）: 创建表、幂等键 `UNIQUE(user_id, level)`。
- [ ] **数据迁移脚本**: 兼容旧 `bv_logs` 历史数据（可选，仅用于对账辅助）。

**验收标准**:
- 所有新表成功创建，幂等键与索引生效。
- 能通过数据库工具查询到表结构与索引定义。

---

### 第二阶段：订单 PV 入账与实时奖金（Week 2-3）
**目标**: 实现订单发货触发 PV 入账、直推奖/层碰奖实时发放、莲子 A/B 产出。

**任务清单**:
- [ ] **PVLedgerService**: 
  - `creditPVFromOrder($order)`: 沿安置链向上累加左右区 PV，写入 `pv_ledger`，并更新 `user_extras.bv_left/bv_right`。
  - 支持幂等：同一 `orders.trx` 不重复入账。
- [ ] **BonusService::issueDirectBonus($order)**:
  - 计算直推奖 20%，检查收款人（Sponsor）是否已激活。
  - 已激活：写入 `transactions`（`remark=direct_bonus`）。
  - 未激活：写入 `pending_bonuses`（`bonus_type=direct`，`accrued_week_key=当前周`）。
- [ ] **BonusService::triggerLevelPairBonus($order)**:
  - 回溯安置链，计算相对层级 level + 侧位（L/R）。
  - 更新 `user_level_hits`：首次点亮 left/right；两侧均点亮触发层碰奖 25%。
  - 已激活：写入 `transactions`（`remark=level_pair_bonus`）。
  - 未激活：写入 `pending_bonuses`（`bonus_type=level_pair`）。
- [ ] **PointsService::creditSelfPurchase / creditDirectReferral**:
  - 自购 A：+3000×数量；直推 B：+1500×数量。
  - 写入 `user_assets` + `user_points_log`（`source_type=PURCHASE/DIRECT`，`reference_id=orders.trx`）。
- [ ] **事务边界**: 以上所有动作在同一数据库事务内完成（或使用可靠消息队列补偿）。

**验收标准**:
- 订单发货后，PV 沿安置链累加成功，可在 `pv_ledger` 查到多条记录。
- 直推奖/层碰奖根据激活状态正确进入余额或待处理。
- 莲子 A/B 正确产出，且同一订单重复触发不产生重复积分。
- 所有流水可通过 `orders.trx` 追溯。

---

### 第三阶段：周结算引擎（Week 3-5）
**目标**: 实现周结算 Cron/手动触发、对碰/管理奖计算、K 值折算、PV 扣减、莲子 TEAM 产出。

**任务清单**:
- [ ] **SettlementService::executeWeeklySettlement($week_key, $dryRun=false)**:
  - **Step 1**: 统计本周刚性支出（FixedSales = 已发直推/层碰 + 待处理应计预留）。
  - **Step 2**: 统计本周总 PV（从 `pv_ledger` 按 `source_type=order` + `trx_type=+` 统计）。
  - **Step 3**: 功德池预留（4% = 护持池 1% + 领航池 3%）。
  - **Step 4**: 逐用户计算对碰理论（WeakPV / 3000 × 300）并应用周封顶（仅对碰）。
  - **Step 5**: 逐用户计算管理奖理论（基于下级对碰实发或理论 × 10%~5%）。
  - **Step 6**: 计算 K 值（Remaining = TotalCap - GlobalReserve - FixedSales；K = min(1, Remaining / A)）。
  - **Step 7**: 发放对碰/管理实发（乘以 K），写入 `transactions`（`remark=pair_bonus/matching_bonus`，`details` 含 `week_key`）。
  - **Step 8**: PV 扣减（仅扣减已发放对碰的 PV），写入 `pv_ledger`（`trx_type=-`，`source_type=weekly_settlement`，`source_id=week_key`）。
  - **Step 9**: 莲子 TEAM 产出（WeakPV × 0.1），写入 `user_points_log`（`source_type=TEAM`，`reference_id=week_key`）。
  - **Step 10**: 写入 `weekly_settlements` + `weekly_settlement_user_summaries`，并标记 `finalized_at`。
- [ ] **并发控制**: 使用分布式锁（Redis）或数据库行锁，确保同一 `week_key` 不被并发执行。
- [ ] **预演模式（Dry Run）**: 当 `dryRun=true` 时，执行计算但不写入流水，仅返回 JSON 结果。

**验收标准**:
- 手动/自动触发周结算后，`weekly_settlements` 写入 1 条记录，包含正确的 K 值。
- 每个用户 `weekly_settlement_user_summaries` 写入 1 条记录，含对碰/管理理论/实发/封顶/K。
- PV 仅扣减已发放对碰部分，剩余 PV 保留。
- 莲子 TEAM 正确产出，且同一 `week_key` 重复执行不产生重复积分。
- 预演模式可正常返回计算结果，不写入流水。

---

### 第四阶段：季度分红引擎（Week 5-6）
**目标**: 实现季度分红 Cron/手动触发、1% 消费商池 + 3% 领导人池分配。

**任务清单**:
- [ ] **SettlementService::executeQuarterlySettlement($quarter_key, $dryRun=false)**:
  - **1% 消费商池（护持池）**:
    - Pool = QuarterTotalPV × 1%。
    - Eligible：个人累计请购 ≥ 3 台。
    - Active：当季直推 1 单或自购 1 单（检查 `last_activity_date` 是否在季度内）。
    - TotalShares = Σ(Shares)（仅统计 Eligible 且 Active）。
    - UnitValue = Pool / TotalShares。
    - 逐用户分红 = Shares × UnitValue，写入 `transactions`（`remark=stockist_dividend`）+ `dividend_logs`（`status=paid`）。
    - 未达标/未活跃：写入 `dividend_logs`（`status=skipped`），池留存/滚入下一季（不做后续释放）。
  - **3% 领导人池（领航池）**:
    - Pool = QuarterTotalPV × 3%。
    - Eligible：QuarterlyWeakPV ≥ 10,000。
    - 职级判定：历史累计小区 PV 达标 + 架构达标（Sponsor 树）。
    - 职级命名：琉璃行者/黄金导师/玛瑙护法/摩尼大德/金刚尊者（内部码：liuli_xingzhe/huangjin_daoshi/manao_hufa/moni_dade/jingang_zunzhe）。
    - Score = QuarterlyWeakPV × RankMultiplier（W 系数：1/2/3/5/10）。
    - TotalScore = Σ(Score)。
    - UnitValue = Pool / TotalScore。
    - 逐用户分红 = Score × UnitValue，写入 `transactions`（`remark=leader_dividend`）+ `dividend_logs`（`status=paid`）。
    - 未达标：写入 `dividend_logs`（`status=skipped`）。
  - **批次落库**: 写入 `quarterly_settlements`（`quarter_key`、`pool_stockist`、`pool_leader`、`total_shares`、`total_score`、`unit_value_stockist`、`unit_value_leader`、`config_snapshot`、`finalized_at`）。

**验收标准**:
- 季度结算后，`quarterly_settlements` 写入 1 条记录。
- `dividend_logs` 记录所有达标/未达标用户，状态正确（`paid/skipped`）。
- 不活跃/未达标用户的池份额留存，不做后续补发。
- 所有分红流水可通过 `quarter_key` 追溯。

---

### 第五阶段：待处理奖金释放与审核（Week 6）
**目标**: 实现用户激活后自动释放待处理奖金、异常进入人工审核。

**任务清单**:
- [ ] **BonusService::releasePendingBonusesOnActivation($user_id)**:
  - 触发时机：用户首次激活（`personal_purchase_count` 从 0 变为 ≥1）。
  - 查询 `pending_bonuses`（`recipient_id=$user_id`，`status=pending`，`release_mode=auto`）。
  - 逐条释放：写入 `transactions`（`remark=pending_bonus_release`，`details` 含原 `source_id`），并更新 `pending_bonuses.status=released`、`released_trx`。
- [ ] **后台审核界面**:
  - 列表：显示 `status=pending` 且 `release_mode=manual` 的待处理奖金。
  - 操作：审核通过/拒绝，写入 `audit_logs`（`action_type=release_pending/reject_pending`）。

**验收标准**:
- 用户激活后，待处理奖金自动释放到余额，且 `pending_bonuses.status=released`。
- 异常待处理奖金进入后台审核列表，可手动通过/拒绝。
- 所有释放流水可通过 `pending_bonuses.released_trx` 追溯。

---

### 第六阶段：退款与冲正（Week 6-7）
**目标**: 实现发货后退款的负向流水冲正、调整批次、负余额与提现限制。

**任务清单**:
- [ ] **AdjustmentService::createRefundAdjustment($order, $reason)**:
  - 若退款发生在订单所属周 Finalize 前：自动创建 `adjustment_batches`（`reason_type=refund_before_finalize`），并自动 `finalized_at`。
  - 若退款发生在 Finalize 后：进入人工审核，创建 `adjustment_batches`（`reason_type=refund_after_finalize`），等待审核确认。
- [ ] **AdjustmentService::finalizeAdjustmentBatch($batch_id)**:
  - 批量生成负向流水：
    - `pv_ledger`: `-` PV，填写 `reversal_of_id`（原订单 PV 入账的 `pv_ledger.id`）。
    - `transactions`: `-` 直推/层碰奖金，填写 `adjustment_batch_id`。
    - `user_points_log`: `-` 莲子 A/B，填写 `reversal_of_id`。
  - 更新 `adjustment_batches.finalized_at`，写入 `audit_logs`（`action_type=adjustment_finalize`）。
- [ ] **负余额与提现限制**:
  - 钱包余额 `users.balance` 允许为负。
  - 提现接口检查：若 `balance < 0`，禁止新提现并提示"余额不足，后续收益优先抵扣"。

**验收标准**:
- 发货后退款能正确冲正 PV/奖金/积分，且 `reversal_of_id` 关联到原始流水。
- 冲正后余额可为负，提现被正确限制。
- Finalize 后退款进入人工审核流程，审核确认后生成负向流水。

---

### 第七阶段：后台管理与报表（Week 7-8）
**目标**: 实现后台配置、周/季度结算手动触发、报表导出。

**任务清单**:
- [ ] **制度参数配置**:
  - 创建 `bonus_configs` 表（`version_code`、`config_json`、`is_active`）。
  - 后台界面：新增/编辑/激活制度版本（比例/封顶/周定义/七宝门槛等）。
- [ ] **结算管理**:
  - 周结算：手动触发界面 + 预演模式（Dry Run）。
  - 季度结算：手动触发界面 + 预演模式。
  - 显示历史结算批次列表（`week_key`、`quarter_key`、`finalized_at`、`k_factor`）。
- [ ] **报表导出**:
  - 周结算报表：汇总 + 用户明细（CSV/Excel）。
  - 季度分红报表：1%/3% 明细 + 未达标清单。
  - 待处理奖金报表：余额总额 + 明细。
  - PV 对账报表：按订单/周结算/调整批次分类。
  - 莲子对账报表：按 source_type（PURCHASE/DIRECT/TEAM/DAILY）分类。

**验收标准**:
- 后台可成功配置制度版本并激活。
- 周/季度结算可手动触发，预演模式返回正确结果不写入流水。
- 所有报表可导出，数据与数据库一致。

---

## 2. 风险管理

| 风险点 | 影响 | 应对措施 |
|---|---|---|
| 层碰回溯性能（树过深） | 订单发货慢，用户体验差 | 限制最大回溯层级（如 50 层）或异步队列处理 |
| 周结算全用户遍历慢 | Cron 超时，结算失败 | 分片/分批执行，或增加服务器资源 |
| 幂等键冲突（重复执行） | 数据不一致 | 严格单元测试，确保唯一键约束生效 |
| K 值计算误差 | 拨出超标/资金风险 | 保留 4 位小数精度，严格单元测试验证 |
| 退款冲正复杂度高 | 开发周期长 | 采用"调整批次"方案，避免回滚重算 |
| 历史 bv_logs 数据迁移 | 数据丢失风险 | 仅用于兼容查询，不作为 V10.1 主台账 |

---

## 3. 资源需求

- **后端开发**: 2-3 人（Laravel）
- **前端开发**: 1-2 人（后台 + 用户端）
- **测试人员**: 1 人（单元测试 + 集成测试）
- **DBA/运维**: 1 人（数据库优化 + 部署支持）

---

## 4. 测试计划

### 4.1 单元测试（PHPUnit）
- **订单 PV 入账**: 测试幂等性、事务原子性、PV 累加正确性。
- **直推/层碰奖金**: 测试激活/未激活分支、待处理奖金写入、自动释放。
- **周结算 K 值**: 构造 `Remaining < A`，验证 K 值折算公式正确。
- **周封顶**: 构造用户对碰理论 > 封顶，验证封顶后参与 K（管理不计入封顶）。
- **季度分红**: 测试活跃判定、份数/积分计算、不活跃处理。
- **退款冲正**: 测试负向流水生成、`reversal_of_id` 关联、负余额逻辑。

### 4.2 集成测试
- **完整流程**: 下单 → 发货 → PV 入账 → 实时奖金 → 周结算 → 季度分红 → 退款冲正。
- **追溯链验证**: 从任意一笔交易流水反查到订单/批次/PV 台账。
- **报表一致性**: 导出报表数据与数据库汇总一致。

### 4.3 压力测试（可选）
- **并发订单发货**: 模拟 100+ 订单同时发货，验证 PV 入账不丢失。
- **周结算性能**: 模拟 10000+ 用户，验证周结算在合理时间内完成。

---

## 5. 上线计划

### 5.1 预发布环境验证
- 在预发布环境执行完整流程测试（含真实订单数据模拟）。
- 财务团队确认报表数据无误。

### 5.2 灰度发布
- 先开放部分用户（如内部测试用户）访问 V10.1 功能。
- 观察 1-2 周，无异常后全量发布。

### 5.3 数据备份与回滚预案
- 上线前全量备份数据库。
- 准备回滚脚本（若 V10.1 有严重 bug，可快速回滚到旧版本）。

---

## 6. 需求 → 任务映射清单

| 需求文档章节 | 对应任务 | 里程碑 |
|---|---|---|
| 用户需求文档 6.1 商品与 PV 规则 | 订单 PV 入账 | 第二阶段 |
| 用户需求文档 6.2 身份等级与周封顶 | 周结算周封顶逻辑（仅对碰） | 第三阶段 |
| 用户需求文档 6.3.1 直推奖 | BonusService::issueDirectBonus | 第二阶段 |
| 用户需求文档 6.3.2 层碰奖 | BonusService::triggerLevelPairBonus | 第二阶段 |
| 用户需求文档 6.3.3 对碰奖 | 周结算对碰计算 | 第三阶段 |
| 用户需求文档 6.3.4 管理奖 | 周结算管理奖计算（不计入封顶） | 第三阶段 |
| 用户需求文档 6.3.5 功德池分红 | 季度结算（4%=1%+3%） | 第四阶段 |
| 用户需求文档 6.4 K 值风控 | 周结算 K 值计算 | 第三阶段 |
| 用户需求文档 6.5 七宝进阶 | 季度结算 3% 领导人池（琉璃行者等） | 第四阶段 |
| 用户需求文档 6.6 莲子积分 | 莲子 A/B/C/D 产出 | 第二/三阶段 |
| 结算与对账需求 2.1 订单发货 | 订单 PV 入账事务 | 第二阶段 |
| 结算与对账需求 3 周结算 | SettlementService::executeWeeklySettlement | 第三阶段 |
| 结算与对账需求 4 季度分红 | SettlementService::executeQuarterlySettlement | 第四阶段 |
| 结算与对账需求 6 退款与冲正 | AdjustmentService | 第六阶段 |
| 数据字典 pv_ledger | 数据库建模 | 第一阶段 |
| 数据字典 pending_bonuses | 数据库建模 | 第一阶段 |
| 数据字典 weekly_settlements | 数据库建模 | 第一阶段 |
| 数据字典 quarterly_settlements | 数据库建模 | 第一阶段 |
| 数据字典 adjustment_batches | 数据库建模 | 第一阶段 |

---

## 7. V10.1 关键口径确认

- **周封顶**: 仅对碰奖封顶，管理奖不计入封顶但参与 K 值折算。
- **五重福报**: 直推奖、层碰奖、对碰奖、管理奖、分红奖（功德池 4%）。
- **功德池**: 4% = 护持池 1% + 领航池 3%（不参与当周 K 折算）。
- **季度分红**: 当季达标当季分红，未达标/未活跃当季不发放（池留存/滚入下一季）。
- **七宝进阶职级**: 琉璃行者、黄金导师、玛瑙护法、摩尼大德、金刚尊者。
- **K 值作用范围**: 仅作用于对碰奖、管理奖（组织类弹性奖金）。
- **PV 台账**: 新建 `pv_ledger` 作为 V10.1 主台账（替代旧 `bv_logs`）。

---

**结束语**: 本任务计划为 V10.1 落地提供清晰的里程碑与验收标准，所有任务按阶段拆解并映射到需求文档。研发团队应严格按计划推进，财务/测试团队同步介入验收，确保系统上线后可对账、可追溯、可风控。