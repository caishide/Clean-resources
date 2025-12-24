# BinaryEcom API 文档

## 📋 目录

- [概述](#概述)
- [认证方式](#认证方式)
- [通用响应格式](#通用响应格式)
- [错误码说明](#错误码说明)
- [API 接口](#api-接口)
  - [健康检查接口](#健康检查接口)
  - [认证接口](#认证接口)
  - [用户端接口](#用户端接口)
  - [管理员端接口](#管理员端接口)
- [数据模型](#数据模型)
- [业务规则](#业务规则)

---

## 概述

BinaryEcom API 是一个 RESTful API,用于管理直销/MLM 系统的结算、PV 账户、奖金和积分等核心业务。

### 基础信息

- **Base URL**: `https://api.binaryecom.com/api`
- **API 版本**: v1
- **认证方式**: Laravel Sanctum (Bearer Token)
- **数据格式**: JSON
- **字符编码**: UTF-8

### 技术栈

- **框架**: Laravel 11
- **PHP 版本**: 8.3+
- **认证**: Laravel Sanctum
- **数据库**: MySQL 8.0+
- **缓存**: Redis

---

## 认证方式

### Bearer Token 认证

除了公开接口外,所有 API 请求都需要在 HTTP Header 中携带认证 Token:

```
Authorization: Bearer {your_token}
```

### Token 类型

1. **用户 Token**: 通过用户登录获取,用于用户端接口
2. **管理员 Token**: 通过管理员登录获取,用于管理员端接口

### Token 有效期

- 默认有效期: 24 小时
- 可通过配置文件调整

---

## 通用响应格式

### 成功响应

```json
{
  "status": "success",
  "message": "操作成功",
  "data": {
    // 具体数据
  }
}
```

### 错误响应

```json
{
  "status": "error",
  "message": "错误描述",
  "errors": {
    // 详细错误信息
  }
}
```

### 分页响应

```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "data": [],
    "first_page_url": "https://api.binaryecom.com/api/admin/settlements?page=1",
    "from": 1,
    "last_page": 10,
    "last_page_url": "https://api.binaryecom.com/api/admin/settlements?page=10",
    "links": [],
    "next_page_url": "https://api.binaryecom.com/api/admin/settlements?page=2",
    "path": "https://api.binaryecom.com/api/admin/settlements",
    "per_page": 20,
    "prev_page_url": null,
    "to": 20,
    "total": 200
  }
}
```

---

## 错误码说明

| HTTP 状态码 | 错误类型 | 说明 |
|------------|---------|------|
| 200 | OK | 请求成功 |
| 201 | Created | 资源创建成功 |
| 400 | Bad Request | 请求参数错误 |
| 401 | Unauthorized | 未认证或 Token 无效 |
| 403 | Forbidden | 无权限访问 |
| 404 | Not Found | 资源不存在 |
| 422 | Unprocessable Entity | 验证失败 |
| 500 | Internal Server Error | 服务器内部错误 |
| 503 | Service Unavailable | 服务不可用 |

---

## API 接口

### 健康检查接口

#### 1. 快速健康检查

**接口**: `GET /health`

**说明**: 快速检查系统健康状态(缓存 30 秒)

**认证**: 无需认证

**请求示例**:
```bash
curl -X GET https://api.binaryecom.com/api/health
```

**响应示例**:
```json
{
  "status": "ok",
  "timestamp": "2025-12-24T15:30:00.000000Z",
  "environment": "production",
  "checks": {
    "database": "ok",
    "app": "ok"
  }
}
```

---

#### 2. 详细健康检查

**接口**: `GET /health/detailed`

**说明**: 详细检查系统各项指标

**认证**: 无需认证

**请求示例**:
```bash
curl -X GET https://api.binaryecom.com/api/health/detailed
```

**响应示例**:
```json
{
  "status": "ok",
  "timestamp": "2025-12-24T15:30:00.000000Z",
  "environment": "production",
  "version": "11.0.0",
  "checks": {
    "database": {
      "status": "ok",
      "message": "Database connection successful",
      "response_time_ms": 12.34,
      "connection": "mysql"
    },
    "cache": {
      "status": "ok",
      "message": "Cache connection successful",
      "driver": "redis"
    },
    "disk_space": {
      "status": "ok",
      "message": "Disk space check",
      "total_gb": 100.0,
      "free_gb": 60.5,
      "used_gb": 39.5,
      "used_percentage": 39.5
    },
    "memory": {
      "status": "ok",
      "message": "Memory usage check",
      "current_mb": 128.5,
      "peak_mb": 256.0,
      "limit_mb": 512.0,
      "usage_percentage": 25.1
    },
    "app": {
      "status": "ok",
      "message": "Application is running",
      "uptime": "10 days, 5 hours, 30 minutes",
      "laravel_version": "11.0.0",
      "php_version": "8.3.0",
      "migrations_table": "exists",
      "cache": "ok"
    }
  }
}
```

---

#### 3. Ping 检查

**接口**: `GET /ping`

**说明**: 极简的存活检查

**认证**: 无需认证

**请求示例**:
```bash
curl -X GET https://api.binaryecom.com/api/ping
```

**响应示例**:
```json
{
  "status": "ok",
  "time": "2025-12-24T15:30:00.000000Z"
}
```

---

### 认证接口

#### 1. 用户登录

**接口**: `POST /auth/login`

**说明**: 用户登录获取访问 Token

**认证**: 无需认证

**请求参数**:
```json
{
  "username": "user123",
  "password": "password123"
}
```

**参数说明**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| username | string | 是 | 用户名 |
| password | string | 是 | 密码 |

**响应示例**:
```json
{
  "status": "success",
  "data": {
    "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567890",
    "type": "user",
    "user": {
      "id": 1,
      "username": "user123",
      "email": "user@example.com"
    }
  }
}
```

**错误响应**:
```json
{
  "status": "error",
  "message": "Invalid credentials"
}
```
HTTP 状态码: 401

---

#### 2. 管理员登录

**接口**: `POST /auth/admin/login`

**说明**: 管理员登录获取访问 Token

**认证**: 无需认证

**请求参数**:
```json
{
  "username": "admin",
  "password": "admin123"
}
```

**参数说明**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| username | string | 是 | 管理员用户名 |
| password | string | 是 | 密码 |

**响应示例**:
```json
{
  "status": "success",
  "data": {
    "token": "2|aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567890",
    "type": "admin",
    "admin": {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com"
    }
  }
}
```

---

#### 3. 注销

**接口**: `POST /auth/logout`

**说明**: 注销当前 Token

**认证**: 需要认证 (Bearer Token)

**请求头**:
```
Authorization: Bearer {your_token}
```

**响应示例**:
```json
{
  "status": "success",
  "message": "Logged out successfully"
}
```

---

#### 4. 获取当前用户信息

**接口**: `GET /auth/me`

**说明**: 获取当前登录用户的信息

**认证**: 需要认证 (Bearer Token)

**请求头**:
```
Authorization: Bearer {your_token}
```

**响应示例**:
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "username": "user123",
    "email": "user@example.com",
    "type": "user"
  }
}
```

---

### 用户端接口

#### 1. 获取用户 PV 概览

**接口**: `GET /user/pv-summary`

**说明**: 获取用户的 PV(业绩值)概览信息

**认证**: 需要认证 (用户 Token)

**请求头**:
```
Authorization: Bearer {your_token}
```

**查询参数**:
| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| include_carry | boolean | 否 | true | 是否包含结转 PV |

**请求示例**:
```bash
curl -X GET "https://api.binaryecom.com/api/user/pv-summary?include_carry=true" \
  -H "Authorization: Bearer {your_token}"
```

**响应示例**:
```json
{
  "status": "success",
  "data": {
    "include_carry": true,
    "left_pv": 15000,
    "right_pv": 12000,
    "weak_pv": 12000,
    "this_week_left": 3000,
    "this_week_right": 2500
  }
}
```

**字段说明**:
| 字段 | 类型 | 说明 |
|------|------|------|
| left_pv | number | 左区 PV 总量 |
| right_pv | number | 右区 PV 总量 |
| weak_pv | number | 弱区 PV (左右区较小值) |
| this_week_left | number | 本周左区新增 PV |
| this_week_right | number | 本周右区新增 PV |

---

#### 2. 获取用户积分概览

**接口**: `GET /user/points-summary`

**说明**: 获取用户的莲子积分概览

**认证**: 需要认证 (用户 Token)

**请求头**:
```
Authorization: Bearer {your_token}
```

**请求示例**:
```bash
curl -X GET "https://api.binaryecom.com/api/user/points-summary" \
  -H "Authorization: Bearer {your_token}"
```

**响应示例**:
```json
{
  "status": "success",
  "data": {
    "total_points": 5000,
    "a_class": 2000,
    "b_class": 1500,
    "c_class": 1000,
    "d_class": 500
  }
}
```

**字段说明**:
| 字段 | 类型 | 说明 |
|------|------|------|
| total_points | number | 总积分 |
| a_class | number | A 类积分 |
| b_class | number | B 类积分 |
| c_class | number | C 类积分 |
| d_class | number | D 类积分 |

---

#### 3. 获取用户奖金历史

**接口**: `GET /user/bonus-history`

**说明**: 获取用户的奖金历史记录

**认证**: 需要认证 (用户 Token)

**请求头**:
```
Authorization: Bearer {your_token}
```

**查询参数**:
| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| start_date | string | 否 | 一个月前 | 开始日期 (YYYY-MM-DD) |
| end_date | string | 否 | 今天 | 结束日期 (YYYY-MM-DD) |

**请求示例**:
```bash
curl -X GET "https://api.binaryecom.com/api/user/bonus-history?start_date=2025-11-01&end_date=2025-12-01" \
  -H "Authorization: Bearer {your_token}"
```

**响应示例**:
```json
{
  "status": "success",
  "data": {
    "period": {
      "start": "2025-11-01",
      "end": "2025-12-01"
    },
    "direct_bonus": {
      "total_amount": 5000.00,
      "count": 10,
      "average": 500.00
    },
    "level_pair_bonus": {
      "total_amount": 3000.00,
      "count": 5,
      "average": 600.00
    }
  }
}
```

---

#### 4. 获取待处理奖金

**接口**: `GET /user/pending-bonuses`

**说明**: 获取用户的待处理奖金列表

**认证**: 需要认证 (用户 Token)

**请求头**:
```
Authorization: Bearer {your_token}"
```

**响应示例**:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "type": "pair_bonus",
      "amount": 500.00,
      "status": "pending",
      "created_at": "2025-12-20T10:00:00.000000Z"
    },
    {
      "id": 2,
      "type": "matching_bonus",
      "amount": 300.00,
      "status": "pending",
      "created_at": "2025-12-21T10:00:00.000000Z"
    }
  ]
}
```

---

### 管理员端接口

#### 1. 获取周结算列表

**接口**: `GET /admin/settlements`

**说明**: 获取周结算历史记录

**认证**: 需要认证 (管理员 Token)

**请求头**:
```
Authorization: Bearer {admin_token}
```

**查询参数**:
| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| page | integer | 否 | 1 | 页码 |
| per_page | integer | 否 | 20 | 每页数量 |

**请求示例**:
```bash
curl -X GET "https://api.binaryecom.com/api/admin/settlements?page=1&per_page=20" \
  -H "Authorization: Bearer {admin_token}"
```

**响应示例**:
```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "week_key": "2025-W51",
        "status": "completed",
        "total_users": 1000,
        "total_pair_bonus": 50000.00,
        "total_matching_bonus": 30000.00,
        "created_at": "2025-12-22T00:00:00.000000Z",
        "finalized_at": "2025-12-22T02:00:00.000000Z"
      }
    ],
    "first_page_url": "https://api.binaryecom.com/api/admin/settlements?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "https://api.binaryecom.com/api/admin/settlements?page=5",
    "links": [],
    "next_page_url": "https://api.binaryecom.com/api/admin/settlements?page=2",
    "path": "https://api.binaryecom.com/api/admin/settlements",
    "per_page": 20,
    "prev_page_url": null,
    "to": 20,
    "total": 100
  }
}
```

---

#### 2. 执行周结算预演

**接口**: `POST /admin/settlements/dry-run`

**说明**: 执行周结算预演(不实际写入数据)

**认证**: 需要认证 (管理员 Token)

**请求头**:
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**请求参数**:
```json
{
  "week": "2025-W51"
}
```

**参数说明**:
| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| week | string | 否 | 上周 | 周标识 (格式: YYYY-Www) |

**请求示例**:
```bash
curl -X POST "https://api.binaryecom.com/api/admin/settlements/dry-run" \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{"week": "2025-W51"}'
```

**响应示例**:
```json
{
  "status": "success",
  "message": "预演完成",
  "data": {
    "week": "2025-W51",
    "preview": {
      "total_users": 1000,
      "total_pair_bonus": 50000.00,
      "total_matching_bonus": 30000.00,
      "k_factor": 0.85
    }
  }
}
```

---

#### 3. 执行周结算

**接口**: `POST /admin/settlements/execute`

**说明**: 执行周结算(实际写入数据)

**认证**: 需要认证 (管理员 Token)

**请求头**:
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**请求参数**:
```json
{
  "week": "2025-W51",
  "confirmed": true
}
```

**参数说明**:
| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| week | string | 否 | 上周 | 周标识 (格式: YYYY-Www) |
| confirmed | boolean | 否 | false | 是否确认执行 |

**请求示例**:
```bash
curl -X POST "https://api.binaryecom.com/api/admin/settlements/execute" \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{"week": "2025-W51", "confirmed": true}'
```

**响应示例** (未确认):
```json
{
  "status": "pending_confirmation",
  "message": "请确认结算数据",
  "data": {
    "week": "2025-W51",
    "preview": {
      "total_users": 1000,
      "total_pair_bonus": 50000.00,
      "total_matching_bonus": 30000.00
    }
  }
}
```

**响应示例** (已确认):
```json
{
  "status": "success",
  "message": "结算完成",
  "data": {
    "week": "2025-W51",
    "settlement_id": 1,
    "total_users": 1000,
    "total_pair_bonus": 50000.00,
    "total_matching_bonus": 30000.00
  }
}
```

---

#### 4. 获取 K 值计算详情

**接口**: `GET /admin/settlements/{week}/k-factor`

**说明**: 获取指定周的 K 值计算详情

**认证**: 需要认证 (管理员 Token)

**请求头**:
```
Authorization: Bearer {admin_token}"
```

**路径参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| week | string | 是 | 周标识 (格式: YYYY-Www) |

**请求示例**:
```bash
curl -X GET "https://api.binaryecom.com/api/admin/settlements/2025-W51/k-factor" \
  -H "Authorization: Bearer {admin_token}"
```

**响应示例**:
```json
{
  "status": "success",
  "data": {
    "week": "2025-W51",
    "k_factor": 0.85,
    "calculation": {
      "total_sales": 1000000.00,
      "total_bonus_cap": 700000.00,
      "calculated_k": 0.85
    }
  }
}
```

---

#### 5. 批量释放待处理奖金

**接口**: `POST /admin/bonuses/release`

**说明**: 批量释放待处理的奖金

**认证**: 需要认证 (管理员 Token)

**请求头**:
```
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**请求参数**:
```json
{
  "bonus_ids": [1, 2, 3, 4, 5]
}
```

**参数说明**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| bonus_ids | array | 是 | 奖金 ID 列表 |

**请求示例**:
```bash
curl -X POST "https://api.binaryecom.com/api/admin/bonuses/release" \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{"bonus_ids": [1, 2, 3, 4, 5]}'
```

**响应示例**:
```json
{
  "status": "success",
  "message": "释放完成",
  "data": {
    "total": 5,
    "success": 5,
    "failed": 0,
    "details": [
      {
        "bonus_id": 1,
        "status": "released"
      },
      {
        "bonus_id": 2,
        "status": "released"
      }
    ]
  }
}
```

**错误响应**:
```json
{
  "status": "error",
  "message": "请选择要释放的奖金"
}
```
HTTP 状态码: 400

---

## 数据模型

### User (用户)

```json
{
  "id": 1,
  "username": "user123",
  "email": "user@example.com",
  "status": 1,
  "balance": 1000.00,
  "created_at": "2025-01-01T00:00:00.000000Z",
  "updated_at": "2025-12-24T15:30:00.000000Z"
}
```

### PvLedger (PV 账户)

```json
{
  "id": 1,
  "user_id": 1,
  "from_user_id": 2,
  "position": 1,
  "level": 1,
  "amount": 3000,
  "trx_type": "+",
  "source_type": "order",
  "source_id": "TRX123456",
  "created_at": "2025-12-24T10:00:00.000000Z"
}
```

### WeeklySettlement (周结算)

```json
{
  "id": 1,
  "week_key": "2025-W51",
  "status": "completed",
  "total_users": 1000,
  "total_pair_bonus": 50000.00,
  "total_matching_bonus": 30000.00,
  "k_factor": 0.85,
  "created_at": "2025-12-22T00:00:00.000000Z",
  "finalized_at": "2025-12-22T02:00:00.000000Z"
}
```

### Transaction (交易记录)

```json
{
  "id": 1,
  "user_id": 1,
  "trx_type": "+",
  "amount": 500.00,
  "remark": "pair_bonus",
  "source_type": "weekly_settlement",
  "source_id": "2025-W51",
  "post_balance": 1500.00,
  "created_at": "2025-12-22T01:00:00.000000Z"
}
```

---

## 业务规则

### PV (业绩值) 规则

1. **PV 单位**: 1 PV = 3000 元
2. **PV 计算**: 订单金额 / 3000 = PV 数量
3. **PV 分配**: 订单产生的 PV 按安置链向上分配
4. **PV 结转**: 每周结算后,未结算的 PV 可结转到下周

### 奖金规则

1. **对碰奖**:
   - 左右区 PV 对碰产生奖金
   - 对碰比例: 10%
   - 对碰单位: 300 元/对
   - 周封顶: 根据等级不同

2. **管理奖**:
   - 根据下级对碰奖的一定比例发放
   - 比例根据等级和代数不同

3. **总拨出比例**:
   - 最高不超过总销售额的 70%
   - 通过 K 值调整实际拨出比例

### 结算规则

1. **结算周期**: 每周一次
2. **结算时间**: 每周一凌晨
3. **结算流程**:
   - 计算所有用户的 PV
   - 计算对碰奖
   - 计算管理奖
   - 应用 K 值调整
   - 生成奖金记录
   - 结转未结算 PV

### 积分规则

1. **积分类型**: A、B、C、D 四类
2. **积分获取**: 购买产品、推荐用户等
3. **积分用途**: 兑换商品、抵扣现金等

---

## 附录

### 常见问题

**Q: Token 过期怎么办?**  
A: 重新调用登录接口获取新的 Token。

**Q: 如何获取上周的周标识?**  
A: 周标识格式为 `YYYY-Www`,例如 `2025-W51` 表示 2025 年第 51 周。

**Q: 结算预演和正式结算有什么区别?**  
A: 预演不会实际写入数据,仅用于查看结算结果;正式结算会实际写入数据并不可逆。

**Q: 如何查看结算是否成功?**  
A: 调用 `/admin/settlements` 接口查看结算列表,状态为 `completed` 表示结算成功。

### 更新日志

| 版本 | 日期 | 说明 |
|------|------|------|
| v1.0.0 | 2025-12-24 | 初始版本 |

### 联系方式

- **技术支持**: support@binaryecom.com
- **API 文档**: https://docs.binaryecom.com
- **开发者门户**: https://developer.binaryecom.com

---

**文档版本**: v1.0.0  
**最后更新**: 2025-12-24  
**维护团队**: BinaryEcom 开发团队
