# 动态多仓路由 — 部署文档

本模块为「动态多仓路由（Dynamic Multi-Warehouse Routing）」能力的部署与验收说明，覆盖**环境变量、队列任务、迁移与种子、验收命令**四个方面。

## 0. 模块构成速览

| 关注点 | 实现位置 | 说明 |
|--------|----------|------|
| 路由决策 | [InventoryRoutingService.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/app/Services/InventoryRoutingService.php) | 多策略链：国家匹配 → 偏好仓 → 优先级 → 主仓 → 首可用，含 CN MOQ 兜底 |
| 状态管理 | [ChannelInventoryStateManager.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/app/Services/ChannelInventoryStateManager.php) | 仓源同步、停用联动、绑定重建（事务内） |
| 渠道模型 | [Channel.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/app/Models/Channel.php) | `inventorySources()` 仅暴露活跃仓；停用仓通过 `removeInactiveInventorySources()` 清理 |
| 仓源模型 | [InventorySource.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/app/Models/InventorySource.php) | `booted()` 钩子在停用/删除时触发联动重建 |
| API 入口 | [ChannelController.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/app/Http/Controllers/Api/ChannelController.php) | `routing-order` / `primary-source` / `route-source` / `can-route` 等端点 |
| 路由表 | [routes/api.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/routes/api.php) | 全部置于 `auth:sanctum` 中间件之下 |

路由策略链优先级（自上而下，命中即返回）：

1. `country_match` — 请求国别与仓源国别直接命中
2. `cn_moq_fallback` — 国别未命中但存在 `CN` 仓（`is_moq_direct=true`、`fallback_to_cn=true`）
3. `preferred_source` — 指定偏好仓且该仓已绑定且活跃
4. `priority_match` — 仓源 `priority ≥ min_priority`
5. `primary` — 绑定的主仓（`is_primary=true`）
6. `first_available` — 排序后第一个可用仓

---

## 1. 环境变量

> 配置文件来源：[config/app.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/config/app.php)、[config/database.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/config/database.php)、[config/queue.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/config/queue.php)。多仓路由本身为代码驱动，无独立业务开关变量；以下变量为该模块运行所依赖的基础设施变量。

### 1.1 应用基础

```dotenv
APP_NAME="CRM Routing"
APP_ENV=production            # local / testing / production
APP_DEBUG=false               # 生产必须为 false
APP_URL=https://routing.example.com
APP_KEY=                      # 部署首步必须执行 php artisan key:generate 生成
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=cache  # file / cache（多机部署用 cache）
APP_MAINTENANCE_STORE=database
```

### 1.2 数据库（默认 SQLite，可切 MySQL/MariaDB）

仓源、渠道、绑定关系、队列任务表均落库，必须配置可写连接。

```dotenv
# —— SQLite（本地零配置，开箱即用）——
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
DB_FOREIGN_KEYS=true          # 外键约束务必开启，cascade 删除依赖此项

# —— MySQL / MariaDB（生产推荐）——
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=crm_routing
# DB_USERNAME=app
# DB_PASSWORD=secret
# DB_CHARSET=utf8mb4
# DB_COLLATION=utf8mb4_unicode_ci
# DB_SOCKET=
# MYSQL_ATTR_SSL_CA=          # 如需 SSL 连接则填 CA 证书路径
```

> 注意：[channel_inventory_source 迁移](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/database/migrations/2026_06_21_000102_create_channel_inventory_source_table.php) 使用了 `cascadeOnDelete()`，`DB_FOREIGN_KEYS=true` 是其生效前提。

### 1.3 队列（默认 database）

```dotenv
QUEUE_CONNECTION=database      # sync / database / redis / sqs / beanstalkd / failover
DB_QUEUE_CONNECTION=null       # null 表示用默认库连接，可单独指定队列库
DB_QUEUE_TABLE=jobs            # 与迁移 jobs 表名一致
DB_QUEUE=default               # 队列名
DB_QUEUE_RETRY_AFTER=90        # 秒，超时后任务可被重试

QUEUE_FAILED_DRIVER=database-uuids   # database-uuids / file / dynamodb / null
```

可选 Redis 队列 / 缓存（切换 `QUEUE_CONNECTION=redis` 时需要）：

```dotenv
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=90
```

### 1.4 会话与认证（Sanctum）

API 路由统一在 `auth:sanctum` 下（见 [routes/api.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/routes/api.php)），需正确配置：

```dotenv
SESSION_DRIVER=database       # 多机部署勿用 file
SESSION_DOMAIN=routing.example.com
SANCTUM_STATEFUL_DOMAINS=routing.example.com
```

### 1.5 测试环境（phpunit 覆盖）

[phpunit.xml](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/phpunit.xml) 已固化测试环境变量，测试时无需手动设置：`APP_ENV=testing`、`DB_CONNECTION=sqlite`、`DB_DATABASE=:memory:`、`QUEUE_CONNECTION=sync`、`CACHE_STORE=array`。

---

## 2. 队列任务

### 2.1 队列表结构

[jobs 迁移](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/database/migrations/0001_01_01_000002_create_jobs_table.php) 一次性建立三张表：

- `jobs` — 待处理任务
- `job_batches` — 批任务
- `failed_jobs` — 失败任务（按 `uuid` 唯一，`database-uuids` 驱动写入）

> 模块当前的状态联动（[handleSourceDeactivation](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/app/Services/ChannelInventoryStateManager.php#L51-L62)）在 DB 事务内同步执行；队列基础设施为异步化扩展（如大规模仓源停用重建可改投递到队列）与未来 Job 化预留。

### 2.2 启动队列 Worker

```bash
# 生产推荐：work 守护进程（内存常驻、效率高）
php artisan queue:work database --queue=default --tries=3 --backoff=60 --max-time=3600

# 开发调试：listen（代码改动自动生效）
php artisan queue:listen database --tries=1 --timeout=0
```

> [composer.json](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/composer.json) 的 `dev` 脚本已并行启动 `queue:listen --tries=1 --timeout=0`，本地执行 `composer dev` 即可。

### 2.3 Supervisor 配置（生产）

```ini
[program:crm-routing-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/routing/artisan queue:work database --tries=3 --backoff=60
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/routing/storage/logs/worker.log
stopwaitsecs=3600
```

部署后重启 Worker 以加载新代码：

```bash
php artisan queue:restart   # 优雅退出当前任务后重启
```

### 2.4 失败任务运维

```bash
php artisan queue:failed              # 查看失败任务列表
php artisan queue:retry all           # 重试全部失败任务
php artisan queue:retry <uuid>        # 重试单个
php artisan queue:flush               # 清空失败任务记录
```

---

## 3. 迁移与种子

### 3.1 相关迁移清单

| 迁移文件 | 作用 | 模块依赖 |
|----------|------|----------|
| [0001_01_01_000000_create_users_table](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/database/migrations/0001_01_01_000000_create_users_table.php) | 用户/会话/重置令牌 | 认证（API 鉴权前置） |
| [0001_01_01_000001_create_cache_table](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/database/migrations/0001_01_01_000001_create_cache_table.php) | 缓存表 | 维护模式 / 缓存 |
| [0001_01_01_000002_create_jobs_table](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/database/migrations/0001_01_01_000002_create_jobs_table.php) | jobs / job_batches / failed_jobs | 队列 |
| [2026_06_21_000003_add_role_to_users_table](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/database/migrations/2026_06_21_000003_add_role_to_users_table.php) | users 增加 role / title | 权限（[ChannelPolicy](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/app/Policies/ChannelPolicy.php)） |
| [2026_06_21_000100_create_channels_table](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/database/migrations/2026_06_21_000100_create_channels_table.php) | 渠道表 | 核心 |
| [2026_06_21_000101_create_inventory_sources_table](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/database/migrations/2026_06_21_000101_create_inventory_sources_table.php) | 仓源表 | 核心 |
| [2026_06_21_000102_create_channel_inventory_source_table](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/database/migrations/2026_06_21_000102_create_channel_inventory_source_table.php) | 渠道-仓源绑定（pivot） | 核心 |

### 3.2 执行命令

```bash
# 首次部署：建库 + 全量迁移
php artisan migrate --force

# 全量重置 + 种子（仅开发/演练，会清表）
php artisan migrate:fresh --seed --force

# 仅补种子
php artisan db:seed --force
# 或指定 seeder
php artisan db:seed --class=DatabaseSeeder --force

# 回滚最近一批迁移
php artisan migrate:rollback --force
```

### 3.3 种子数据说明

[DatabaseSeeder.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/database/seeders/DatabaseSeeder.php) 预置以下数据，便于联调验收：

- 1 个测试用户：`test@example.com`
- 5 个仓源：`US_WAREHOUSE`、`US_EAST_WH`、`BR_WAREHOUSE`、`EU_WAREHOUSE`、`DROPSHIP_US`
- 3 个渠道及其绑定：
  - `US_CHANNEL` → 主仓 `US_WAREHOUSE`，含 `US_EAST_WH`、`DROPSHIP_US`
  - `BR_CHANNEL` → 主仓 `BR_WAREHOUSE`，含 `US_WAREHOUSE`（跨国兜底）
  - `EU_CHANNEL` → 主仓 `EU_WAREHOUSE`，含 `US_WAREHOUSE`（跨国兜底）

> 该种子结构可直接验证「国家匹配」与「CN/跨国兜底」策略；若需复现 CN MOQ 兜底，可手动新增一个 `country=CN` 的仓源并绑定。

---

## 4. 验收命令

### 4.1 自动化测试

```bash
# 全量测试（先清配置缓存再跑）
composer test
# 等价于
php artisan config:clear && php artisan test

# 仅多仓路由相关单元测试
php artisan test --filter=InventoryRoutingServiceTest

# 状态管理器单元测试
php artisan test --filter=ChannelInventoryStateManagerTest

# 渠道/仓源 API 特性测试
php artisan test --filter=ChannelApiTest
php artisan test --filter=InventorySourceApiTest
php artisan test --filter=ChannelInventorySourceTest

# 异常与策略测试
php artisan test --filter=ExceptionAndPolicyTest
```

测试用例覆盖（见 [tests/](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/tests)）：

- 路由策略链全分支：`country_match`、`cn_moq_fallback`、`preferred_source`、`priority_match`、`primary`、`first_available`
- 仓源停用联动与主仓重建（[ChannelInventoryStateManagerTest](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/tests/Unit/ChannelInventoryStateManagerTest.php)）
- 角色权限矩阵（admin/manager/analyst）与 422/403/404 异常（[ChannelApiTest](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/001-CRM客户跟进系统/tests/Feature/ChannelApiTest.php)）

### 4.2 代码风格检查

```bash
./vendor/bin/pint --test      # 仅检查不改写
./vendor/bin/pint             # 检查并自动修复
```

### 4.3 健康检查与配置自检

```bash
# 健康端点（bootstrap/app.php 注册的 /up）
curl -sS https://routing.example.com/up

# 配置缓存预热（生产）
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 校验环境配置是否就绪
php artisan env                # 查看当前 env
php artisan about              # 输出框架/数据库/队列等运行态摘要
```

### 4.4 接口冒烟测试（curl）

前置：执行种子后用 `test@example.com` 获取令牌（Sanctum），或直接登录拿 session cookie。

```bash
BASE=https://routing.example.com/api

# 1) 查看某渠道可用仓源（应仅返回活跃仓）
curl -sS "$BASE/channels/1/inventory-sources" \
  -H "Authorization: Bearer <token>"

# 2) 查看路由顺序（含 is_primary / sort_order / priority）
curl -sS "$BASE/channels/1/routing-order" \
  -H "Authorization: Bearer <token>"

# 3) 按国别路由（期望 meta.route_type=country_match）
curl -sS -X POST "$BASE/channels/1/route-source" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"country":"BR"}'

# 4) CN 兜底验证（country=JP 但仅有 CN 仓时 → route_type=cn_moq_fallback）
curl -sS -X POST "$BASE/channels/1/route-source" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"country":"JP"}'

# 5) 路由可达性校验
curl -sS -X POST "$BASE/channels/1/can-route" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"inventory_source_id":1}'
```

### 4.5 部署验收 Checklist

```bash
# 一键拉起：依赖 → 配置 → 迁移 → 种子 → 缓存
composer install --no-dev --optimize-autoloader
php artisan key:generate        # 若 APP_KEY 为空
php artisan migrate --force
php artisan db:seed --force      # 演练/首装
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart        # 重启 Worker 加载新代码
composer test                    # 验收测试全绿
```

验收通过标准：

- [x] `php artisan test` 全部通过（含路由策略链与权限矩阵）
- [x] `curl /up` 返回 200
- [x] `/channels/{id}/route-source` 按国别返回预期 `route_type`
- [x] 仓源停用后，相关渠道主仓自动重建、不再暴露停用仓
- [x] 队列 Worker 常驻、`queue:failed` 无持续堆积
