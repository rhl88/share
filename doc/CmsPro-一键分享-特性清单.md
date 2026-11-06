# 一键分享 · 特性清单

> 版本：1.0.0 | 更新日期：2026-11-05
> 应用版本：1.0.0 | 应用 ID：`cmspro.share`

> 本文档系统整理本应用覆盖的 CmsPro v5 框架特性，并标注实现位置，作为二次开发与验收的参考依据。

---

## 一、应用体系架构

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 1 | 目录隔离 | 应用代码全部位于 `app/Apps/CmsproShare/` | `app/Apps/CmsproShare/` |
| 2 | 命名空间隔离 | `App\Apps\CmsproShare\` | 全部 PHP 文件 |
| 3 | 数据库隔离 | 六张表统一 `app_cmspro_share_` 前缀 | `Migrations/`、`Models/` |
| 4 | 视图隔离 | 视图命名空间 `cmspro.share::` | `ServiceProvider::loadViews()` |
| 5 | 配置隔离 | 配置项 code 前缀 `app_cmspro_share_`，内存配置键 `apps.cmspro.share` | `Config/share.php`、`Services/ShareConfig.php` |
| 6 | manifest.json | 应用元数据唯一声明源（菜单、配置、权限、前台路由声明） | `manifest.json` |
| 7 | 零外部应用依赖 | `dependencies.app_ids` 为空，可独立安装；与 qrlink/sitestat 通过钩子可选联动 | `manifest.json` |
| 8 | 单向依赖 | 只调用框架公开能力（HookManager / Route / View / ConfigItem），框架不感知本应用 | `ServiceProvider.php`、`Hooks.php` |

## 二、三终端全覆盖

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 9 | 后台管理端 | `/admin/cmspro/share`，中间件 `web + auth:admin` | `Routes/admin.php`、`Controllers/Admin/` |
| 10 | 后台 API | `/api/admin/cmspro/share`，同上认证 | `Routes/admin_api.php` |
| 11 | 用户中心端 | `/user/cmspro/share`，中间件 `web + auth:web`，数据按 `sharer_id` 隔离 | `Routes/user.php`、`Controllers/User/ShareController.php` |
| 12 | 用户端 API | `/api/user/cmspro/share`，同上认证 | `Routes/user_api.php` |
| 13 | 前台中转端 | `/{access_path}/{code}`（默认 `/share/{code}`），`web + throttle:60,1` | `Routes/home.php`、`Controllers/Home/RedirectController.php` |
| 14 | 前台公开 API | `/api/cmspro/share/*`，`throttle:120,1` | `Routes/home_api.php`、`Controllers/Api/WidgetController.php` |

## 三、路由系统

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 15 | 路由前缀映射 | app_id 点号转斜杠：`cmspro.share` → `cmspro/share`，全小写 URL | `ServiceProvider::registerRoutes()` |
| 16 | 路由文件按端拆分 | admin / admin_api / user / user_api / home / home_api 六个文件 | `Routes/` |
| 17 | 可配置前台路径 | 中转路径由 `access_path` 配置决定，boot 阶段读取并兜底 | `ServiceProvider::getAccessPath()` |
| 18 | 前台路由冲突声明 | `home_routes` 声明 6 个占用路径 | `manifest.json` |
| 19 | 短码路由约束 | `/{code}` 限定 `[A-Za-z0-9_-]+`，避免吞掉其他路径 | `Routes/home.php` |

## 四、数据层

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 20 | 幂等迁移 | 6 个建表迁移，`up()` 检查 `hasTable`，`down()` 用 `dropIfExists` | `Migrations/` |
| 21 | 表与字段备注 | 每个字段 `->comment()`；MySQL 下 `ALTER TABLE ... COMMENT` | `Migrations/` |
| 22 | SQLite 兼容 | 表备注语句仅在 mysql 驱动下执行，保证内存库测试可跑迁移 | `Migrations/` |
| 23 | 时间字段规范 | `create_time` / `update_time`，模型常量 + `$casts` 输出 `Y-m-d H:i:s` | `Models/` |
| 24 | 索引设计 | 场景+对象、分享者、上级码、事件类型+时间复合索引 | `Migrations/` |
| 25 | SQL 升级脚本 | 提供可执行建表脚本、数据验证语句与回滚方案 | `sql/V1.0.0__init_share_tables.sql` |

### 数据表清单

| 表名 | 用途 |
|------|------|
| `app_cmspro_share_channels` | 渠道定义与后台覆盖（启停/排序/模板/自定义渠道） |
| `app_cmspro_share_scenes` | 场景注册快照与后台启用覆盖 |
| `app_cmspro_share_links` | 分享链接（短码、上下文、分享者、上级码、四类计数） |
| `app_cmspro_share_events` | 跟踪事件（create/click/landing/convert，含访客与设备） |
| `app_cmspro_share_invites` | 裂变邀请关系链（link_code + visitor_key 唯一） |
| `app_cmspro_share_daily_stats` | 日聚合统计（日期+场景+渠道+事件类型唯一） |

## 五、钩子系统

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 26 | 监听系统 Filter | `dashboard.stats` 注入今日分享/落地数，第 4 参数传 app_id | `Hooks.php` |
| 27 | 自定义 Filter：`share.types` | 业务应用注册分享场景与元数据解析器 | `Services/SceneService.php` |
| 28 | 自定义 Filter：`share.channels` | 追加或改写分享渠道 | `Services/ChannelService.php` |
| 29 | 自定义 Filter：`share.context` | 改写分享文案与元数据 | `Services/SceneService.php` |
| 30 | 自定义 Filter：`share.link.build` | 改写最终分享链接（可挂短链 / UTM） | `Services/ShareService.php` |
| 31 | 自定义 Action：`share.event.recorded` | 事件落库后通知 | `Services/TrackService.php` |
| 32 | 自定义 Action：`share.converted` | 转化归因成功后通知奖励系统 | `Services/ShareService.php` |
| 33 | 注册钩子传 app_id | 所有自定义监听由业务应用注册时传入其 app_id，禁用自动摘除 | `Hooks.php`、`doc/扩展指南` |

## 六、配置系统

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 34 | 四组配置项 | 基础设置 / 渠道与高级能力 / 裂变与归因 / 统计与报表，共 27 项 | `manifest.json` |
| 35 | 三向同步 | `Install::seedDefaultConfigs()` 以 manifest 为基准，保留用户 value，清理残留 | `Install.php` |
| 36 | 读取优先级 | config_items 表 > Config/share.php > 调用方兜底 | `Services/ShareConfig.php` |
| 37 | 请求内缓存 | ServiceProvider 单例绑定，保存配置后 `flush()` | `ServiceProvider.php`、`Controllers/Admin/SettingController.php` |
| 38 | 能力全可配置 | 微信 JS-SDK / 二维码 / 海报 / 原生分享 / 复制链接 / 邀请关系 / 转化归因 / 奖励事件 / UV 去重 / UTM / 日聚合 均有独立开关 | `manifest.json` |

## 七、权限系统

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 39 | 权限对象格式 | 6 个权限，`name` 全部中文 | `manifest.json` |
| 40 | 后端认证 | 所有后台路由加 `auth:admin`，用户端加 `auth:web` | `ServiceProvider.php` |

## 八、渠道与裂变能力

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 41 | 国内渠道 | 微信好友/朋友圈、QQ、QQ空间、微博、抖音、小红书、钉钉、企业微信 | `Services/ChannelService.php` |
| 42 | 海外渠道 | Facebook、X、LinkedIn、WhatsApp、Telegram、Pinterest、Reddit、LINE、邮件 | `Services/ChannelService.php` |
| 43 | 通用方式 | 复制链接、二维码、分享海报、Web Share API 原生分享 | `Services/ChannelService.php` |
| 44 | 无官方接口降级 | 抖音/小红书/钉钉/企业微信采用复制链接+引导；微信未配 SDK 时展示右上角引导 | `ChannelService::defaults()`、`Assets/js/share.js` |
| 45 | 微信 JS-SDK | AppID/AppSecret、ticket 缓存、SHA1 签名，未配置友好降级 | `Services/WechatService.php` |
| 46 | 二维码与海报 | endroid/qr-code SvgWriter 纯矢量合成，零 GD 依赖、不落盘 | `Services/PosterService.php` |
| 47 | 裂变关系链 | 落地绑定上下级，支持 1-3 级、归因窗口、访客登录补绑 | `Services/TrackService.php` |
| 48 | 转化归因 | 业务上报注册/下单，按分享链路归因并发奖励事件，本应用不内置结算 | `ShareService::convert()` |
| 49 | 全链路跟踪 | 生成→点击→落地→转化四段漏斗，UV 去重、设备识别、UTM 参数 | `Services/TrackService.php`、`StatService.php` |
| 50 | 日聚合与清理 | 覆盖式日聚合，趋势图读聚合表；原始事件按保留天数清理 | `TrackService::aggregate()/cleanExpired()` |

## 九、安装 / 卸载 / 升级

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 51 | 迁移兜底 | `runMigrations()` 直接调用 `up()` 并写入 migrations 表 | `Install.php` |
| 52 | 卸载回滚兜底 | 逆序调用 `down()`、删除 migrations 记录，禁止手动 drop 表 | `Install.php` |
| 53 | 渠道种子 | 幂等写入 22 个内置渠道，不覆盖后台调整 | `Install::seedDefaultChannels()` |
| 54 | 配置清理 | 卸载时清理本应用配置组与配置项 | `Install::removeConfigs()` |

## 十、视图规范

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 55 | 完整 HTML 结构 | 后台/用户端页面均有独立 `<html>/<head>/<body>` | `Views/Admin/*`、`Views/User/*` |
| 56 | 用户端不继承布局 | 未使用 `@extends('layouts.user')` | `Views/User/shares/index.blade.php` |
| 57 | 功能子目录 | `channels/`、`scenes/`、`links/`、`stats/`、`settings/`、`shares/` 小写复数 | `Views/` |
| 58 | 列表页与表单页分离 | `index.blade.php` + `form.blade.php`（模式 B，layer.open type:1） | `Views/Admin/channels/` |
| 59 | 固定底部按钮栏 | 表单内容区自适应滚动、底部按钮固定 | `Views/Admin/channels/form.blade.php` |
| 60 | Blade / Layui 冲突处理 | 所有含 `{{# }}` 的模板块用 `@verbatim` 包裹 | `Views/Admin/*/index.blade.php` |
| 61 | 公共资源与 CSRF | pear.css / font-awesome / admin.css / layui.js / pear.js + csrf_token | 各后台视图 |

> 表格对齐规范（《应用视图规范》7.5 节）：所有 Layui 表格均设置 `skin: false`、完整 `page` 配置对象、至少一列 `minWidth` 弹性列，并显式声明表格 `id`；数据看板三张表只初始化一次，查询时用 `table.reload` 更新数据。分页参数保持 `limit`（本应用接口不读 `per_page`）。回归用例见 `Tests/Feature/AdminTableViewTest.php`。

> 设置页分组 Tab：按接口返回的 `group`（对应 4 个 `config_groups`）动态生成 `layui-tab`，插入后调用 `element.render('tab', 'settingsTab')`；所有 Tab 共用同一个 `<form>`，保存时整表收集并跳过 Layui 生成的无 `name` 辅助控件。「重新加载」通过 `element.tabChange` 恢复原分组。回归用例见 `Tests/Feature/AdminSettingsTabTest.php`。

> 分享预览页：复用真实 `share.css` / `share.js` 渲染与 `share_render()` 一致的面板 DOM；渠道表沿用 7.5 节对齐规范。预览走真实 `build()`（写入分享记录与 create 事件），内置演示场景由请求内 `share.types` 钩子注入、不落库。回归用例见 `Tests/Feature/AdminPreviewTest.php`。

> 组件资源与图标：静态资源由框架发布到 `public/apps/{appId}/`，`share_asset()` 按 `apps/cmspro.share/` 生成地址（旧写法 `apps/cmspro/share/` 会返回 HTML 回退页导致样式与 SDK 失效）；按钮图标取渠道 `icon` 字段的 FontAwesome 类名，`share.css` 按 `data-channel` 着色并固定图标宽度，预览页额外提供浅色衬底。回归用例见 `Tests/Feature/AdminPreviewTest.php` 中的资源路径、图标类名与品牌色断言。

## 十一、菜单系统

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 62 | 后台菜单 | 6 个子菜单（渠道 / 场景 / 记录 / 看板 / 设置 / 预览），目录不声明 path，所有菜单声明稳定 code 且全局唯一 | `manifest.json` |
| 63 | 用户端菜单 | 使用 `user_menus` 声明「我的分享」 | `manifest.json` |
| 64 | 图标 | 应用根目录 `icon.svg`，manifest 附 fa 类名兜底 | `icon.svg`、`manifest.json` |

## 十二、测试与验证

| # | 特性 | 说明 | 实现位置 |
|---|------|------|---------|
| 65 | 应用级测试 | 测试放应用自身 `Tests/`，不进框架 `tests/` | `Tests/` |
| 66 | 测试基类 | 手动补建系统表、执行应用迁移、注册应用 Provider | `Tests/ShareTestCase.php` |
| 67 | 覆盖场景 | 正常流程、未注册场景、钩子扩展、UV 去重、配置开关、权限拦截、日期格式、二维码、报表、列表表格对齐、设置页分组 Tab、分享预览真实生成、组件资源路径与渠道图标 | `Tests/Feature/ShareFlowTest.php`、`Tests/Feature/AdminTableViewTest.php`、`Tests/Feature/AdminSettingsTabTest.php`、`Tests/Feature/AdminPreviewTest.php`、`Tests/Unit/ChannelServiceTest.php` |
| 68 | 验证结果 | `php artisan test app/Apps/CmsproShare/Tests` → 55 passed / 381 assertions | 验收记录 |
| 69 | F61 修复 | 前台 `track` / `convert` 端点改手动校验（`Validator::make` + `ApiResponse::error(422)`），普通 POST（无 Accept JSON 头）校验失败返回 JSON 契约而非 302 重定向 HTML（常见问题第 1 章）；`share.js` 上报请求补 `Accept: application/json` 头；新增 `TrackApiValidationTest` 6 用例 | `WidgetController`、`share.js` |
| 70 | 微信扫码弹层 | PC 端（非微信环境）点击「微信好友 / 朋友圈」弹出扫码分享层：复用公开二维码端点生成中转链接二维码（手机扫码即可在微信内转发，本地电脑无需登录微信），并提供「复制文字」（标题+链接）与「复制链接」；微信内行为不变（JS-SDK 或右上角引导）；新增 1 个回归用例 | `share.js`、`share.css` |

## 十三、版本历史

| 版本 | 日期 | 说明 |
|------|------|------|
| 1.0.0 | 2026-11-05 | 初始版本：渠道中台、扩展契约、裂变归因、全链路跟踪、三端与 SDK 全部交付 |
