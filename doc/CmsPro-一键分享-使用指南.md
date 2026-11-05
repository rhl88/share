# 一键分享 · 使用指南

> 版本：1.0.0 | 更新日期：2026-11-05
> 应用版本：1.0.0 | 应用 ID：`cmspro.share`

> 本指南帮助使用者安装、配置、验证「一键分享」应用，并说明各能力开关的作用。
> 面向开发者的接入方式见《CmsPro-一键分享-扩展指南》。

---

## 一、应用概述

「一键分享」是一个**分享中台**：它自身不认识任何业务内容，由业务应用（博客、论坛、商品、自定义应用等）通过钩子注册自己的分享场景，本应用统一负责渠道管理、链接生成、中转跳转、裂变关系绑定与数据跟踪。

**核心特点**

- 一个应用统一对接国内外 22 个内置渠道，后台可启停、排序、改模板，业务应用零改动即可增加渠道
- 任意应用注册自己的内容类型后即可分享，新增业务类型不需要修改本应用代码
- 裂变邀请关系、转化归因、奖励通知、UV 去重、UTM、日聚合报表全部可在后台自由开关
- 对 `cmspro.qrlink`、`cmspro.sitestat` 等应用**零强依赖**，通过 `share.link.build` 钩子可选联动

**环境要求**

| 项目 | 要求 |
|------|------|
| PHP | >= 8.3 |
| CmsPro | >= 5.0.0 |
| 数据库 | MySQL 5.7+ / MariaDB 10.3+（测试环境使用 sqlite :memory:） |
| composer 依赖 | `endroid/qr-code ^6.0`（框架已内置，用于 SVG 二维码） |
| 可选 | 微信 JS-SDK 需公众号 AppID/AppSecret 与已配置的 JS 接口安全域名 |

---

## 二、安装

### 2.1 通过后台应用市场安装

1. 将应用目录打包为 zip（根目录含 `manifest.json`），在后台「应用管理」上传安装
2. 安装流程自动完成：执行 6 个建表迁移 → 注册 4 组配置 / 6 个权限 / 后台与用户端菜单 → 写入 22 个内置渠道种子
3. 安装后在应用列表启用「一键分享」

> `Install.php` 带迁移兜底与配置三向同步：重复安装或升级不会重复建表、不会覆盖已保存的配置值。

### 2.2 验证安装

| 检查项 | 预期结果 |
|--------|---------|
| `apps` 表记录 | `app_id = cmspro.share`，`path` 为相对路径 `app/Apps/CmsproShare` |
| 数据表 | 存在 `app_cmspro_share_channels` / `scenes` / `links` / `events` / `invites` / `daily_stats` 六张表 |
| 渠道种子 | `app_cmspro_share_channels` 有 22 条 `is_builtin=1` 记录 |
| 配置项 | `config_items` 中 `app_cmspro_share_%` 前缀共 27 项 |
| 菜单 | 后台出现「一键分享」分组及 5 个子菜单；用户端出现「我的分享」 |

手工核对 SQL：

```sql
SELECT code, name, status FROM app_cmspro_share_channels ORDER BY sort;
SELECT COUNT(*) FROM config_items WHERE code LIKE 'app_cmspro_share_%';
```

---

## 三、后台功能与配置

### 3.1 页面入口

| 页面 | 地址 | 功能 |
|------|------|------|
| 渠道管理 | `/admin/cmspro/share/channels` | 查看内置/钩子渠道，编辑名称、排序、图标、链接模板，启停渠道 |
| 场景管理 | `/admin/cmspro/share/scenes` | 查看业务应用注册的场景，同步快照、启停覆盖 |
| 分享记录 | `/admin/cmspro/share/links` | 按短码/标题/对象ID/场景检索，查看点击、落地、UV、转化 |
| 数据看板 | `/admin/cmspro/share/stats` | 漏斗、渠道效果、场景效果、热门链接、趋势 |
| 应用设置 | `/admin/cmspro/share/settings` | 四组共 27 项配置，即时生效 |

### 3.2 内置渠道清单

| 区域 | 渠道 | 实现方式 |
|------|------|---------|
| 国内 | 微信好友、朋友圈 | 启用 JS-SDK 时自定义标题/缩略图；未启用时微信内展示「点右上角」引导 |
| 国内 | QQ好友、QQ空间、新浪微博 | 官方 Web 分享端点（链接模板可在后台修改） |
| 国内 | 抖音、小红书、钉钉、企业微信 | 无官方网页分享接口 → 复制链接 + 引导粘贴 |
| 海外 | Facebook、X/Twitter、LinkedIn、WhatsApp、Telegram、Pinterest、Reddit、LINE、邮件 | 官方 Web 分享端点 |
| 通用 | 复制链接、二维码、分享海报、系统分享 | 复制链接 / SVG 二维码 / SVG 海报 / Web Share API |

> 后台「默认展示渠道」配置项为英文逗号分隔的白名单，留空表示展示所有启用渠道；该白名单只约束内置渠道，业务应用通过 `share.channels` 钩子注册的自定义渠道不受限制。

### 3.3 配置项说明（27 项）

**基础设置**

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| `access_path` | `share` | 中转链接路径前缀，改为其他值后需清除路由缓存 |
| `default_channels` | 11 个常用渠道 | 默认展示渠道白名单 |
| `link_ttl_days` | `0` | 分享链接有效期天数，0 = 长期有效 |
| `code_length` | `8` | 短码长度（6-16） |
| `track_enabled` | `1` | 总跟踪开关，关闭后不写事件与统计，跳转仍正常 |
| `data_retention_days` | `90` | 原始事件保留天数，0 = 永久 |
| `default_title` / `default_image` | 空 | 业务应用未提供时的兜底文案与缩略图 |

**渠道与高级能力**

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| `wechat_sdk_enabled` | `0` | 微信 JS-SDK 总开关 |
| `wechat_app_id` / `wechat_app_secret` | 空 | 公众号凭证，仅用于换取 jsapi_ticket |
| `qrcode_enabled` | `1` | 二维码（SVG，无需 GD） |
| `poster_enabled` | `0` | 分享海报（SVG 合成） |
| `native_enabled` | `1` | Web Share API 系统分享面板 |
| `copy_enabled` | `1` | 复制链接类渠道 |
| `wechat_guide_text` | 微信右上角引导语 | 未启用 SDK 时展示 |

**裂变与归因**

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| `invite_enabled` | `1` | 落地时绑定邀请关系 |
| `invite_max_level` | `2` | 关系链层级（1/2/3） |
| `invite_window_days` | `30` | 归因有效期 |
| `convert_enabled` | `1` | 允许业务上报转化 |
| `reward_event_enabled` | `1` | 转化后触发 `share.converted` 事件 |
| `parent_param` | `from` | 业务页面 URL 中标识上级分享码的参数名 |

**统计与报表**

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| `uv_dedup_enabled` | `1` | 按 `md5(ip\|UA\|日期)` 去重 |
| `utm_enabled` | `1` | 在分享中转链接附加 UTM 参数 |
| `utm_source` | `share` | UTM 来源标识 |
| `aggregate_enabled` | `1` | 事件写入时同步累加日聚合表 |
| `report_default_days` | `30` | 看板默认时间范围 |

### 3.4 启用微信 JS-SDK 的步骤

1. 在「应用设置 → 渠道与高级能力」打开 `wechat_sdk_enabled`，填写 AppID / AppSecret
2. 在微信公众平台配置「JS 接口安全域名」为本站点域名
3. 业务页面引入微信 JSSDK 脚本，并把 `share_render()` 返回的 SDK 数据挂到页面：
   ```javascript
   window.cmsproShareSdk = { wechat: @json($shareData['sdk']['wechat']) };
   ```
4. 前端 SDK 会自动调用 `wx.updateAppMessageShareData` / `wx.updateTimelineShareData`；凭证缺失或接口失败时自动降级为「右上角分享」引导，不会阻断分享

> 签名使用的 URL 必须是当前页面完整地址（不含 `#` 及之后部分）。若页面为 SPA 且路由变化，需在路由变化后重新取签名。

---

## 四、功能验证

### 4.1 前台中转与公开接口

| 方法 | 地址 | 说明 |
|------|------|------|
| GET | `/share/{code}` | 中转跳转：记录落地事件、绑定邀请关系后 302 到内容原链接；失效时显示提示页 |
| GET | `/api/cmspro/share/qrcode?data=xxx&size=300` | 返回 SVG 二维码 |
| GET | `/api/cmspro/share/poster/{code}` | 返回 SVG 海报（需开启 `poster_enabled`） |
| GET | `/api/cmspro/share/widget/{code}` | 返回纯 JS SDK 所需的渠道与上下文数据 |
| POST | `/api/cmspro/share/track` | 上报 `click` / `landing` 事件（`code`、`event_type`、`channel_code`） |
| POST | `/api/cmspro/share/convert` | 上报转化（`code`、`event`、可选 `user_id` 及业务扩展字段） |

### 4.2 后台接口

| 方法 | 地址 | 说明 |
|------|------|------|
| GET/POST | `/api/admin/cmspro/share/channels`、`/channels/save`、`/channels/seed` | 渠道列表 / 保存 / 种子同步 |
| GET/POST | `/api/admin/cmspro/share/scenes`、`/scenes/save`、`/scenes/sync` | 场景列表 / 覆盖 / 同步 |
| GET/POST | `/api/admin/cmspro/share/links`、`/links/{id}`、`/links/{id}/toggle` | 分享记录 |
| GET | `/api/admin/cmspro/share/stats?start=&end=` | 看板数据 |
| GET/POST | `/api/admin/cmspro/share/settings` | 配置读取与保存 |

### 4.3 用户端

| 方法 | 地址 | 说明 |
|------|------|------|
| GET | `/user/cmspro/share` | 我的分享 / 我的邀请页面 |
| GET | `/api/user/cmspro/share/mine`、`/invites` | 当前登录用户的分享与邀请数据 |

### 4.4 一次完整验证路径

1. 业务应用按《扩展指南》注册一个场景（如 `blog.article`）
2. 在业务模板加一行 `{!! share_render('blog.article', $article->id) !!}`
3. 前台点击任一渠道 → 生成分享中转链接、写入 `links` 与 `create` 事件
4. 打开该中转链接 → 302 到原内容页，写入 `landing` 事件，`visit_count` / `uv_count` 累加，按开关绑定邀请关系
5. 业务侧在注册/下单成功后调用 `ShareService::convert()` → 写入 `convert` 事件并触发 `share.converted`
6. 后台「数据看板」查看漏斗与各维度报表

---

## 五、卸载验证

| 检查项 | 预期结果 |
|--------|---------|
| 六张 `app_cmspro_share_*` 表 | 由迁移 `down()` 逆序删除，无残留 |
| `migrations` 表 | 本应用 6 条记录被清理 |
| 配置组与配置项 | `app_cmspro_share_` 前缀全部删除 |
| 菜单与权限 | 随应用卸载由系统级联清理 |
| 业务应用 | 不受影响（本应用未修改任何业务应用与框架文件） |

> 卸载前请自行备份分享与跟踪数据：卸载会删除业务表且不可逆。正常卸载由 `Install::uninstall()` 走迁移 `down()`，禁止手工 `DROP TABLE`。

---

## 六、目录结构

```
CmsproShare/
├── manifest.json                 # 应用元数据（菜单/配置/权限/路由声明）
├── ServiceProvider.php           # 三端路由、视图、配置、helpers、钩子注册
├── Hooks.php                     # 监听 dashboard.stats
├── Install.php                   # 安装/卸载/升级、迁移兜底、配置三向同步、渠道种子
├── helpers.php                   # share_render / share_url / share_config / share_is_installed
├── icon.svg
├── README.md
├── Config/share.php              # 默认配置
├── Routes/                       # admin / admin_api / user / user_api / home / home_api
├── Controllers/                  # Admin / User / Home / Api
├── Services/                     # ShareConfig / Channel / Scene / Share / Track / Stat / Wechat / Poster
├── Models/                       # 6 个模型
├── Migrations/                   # 6 个幂等建表迁移
├── Views/                        # Admin / User / Home 三端视图
├── Assets/                       # js/share.js（前端 SDK）+ css/share.css
├── Tests/                        # 应用级测试（30 个用例）
├── doc/                          # 特性清单 / 使用指南 / 扩展指南
└── sql/V1.0.0__init_share_tables.sql
```

---

## 七、常见问题

**Q：渠道按钮没显示？**
A：依次检查 ①应用是否已启用；②场景是否在「场景管理」被禁用；③「默认展示渠道」白名单是否排除了该渠道；④对应能力开关（`qrcode_enabled` / `poster_enabled` / `copy_enabled` / `native_enabled`）是否关闭。

**Q：分享链接打开后提示失效？**
A：检查 `links.status` 是否为 1、`expire_time` 是否过期（`link_ttl_days`），以及短码是否被改写（如被 `share.link.build` 钩子挂了外部短链）。

**Q：微信里分享出去没有缩略图和自定义标题？**
A：微信网页分享的标题/缩略图必须由公众号 JS-SDK 配置，需开启 `wechat_sdk_enabled` 并正确填写凭证与 JS 安全域名；未配置时只能使用「点右上角分享」引导。

**Q：抖音/小红书为什么是复制链接？**
A：这些平台没有公开的网页分享接口，业界通行做法是复制链接后引导用户粘贴。可通过 `copy_enabled` 统一开关。

**Q：改了 `access_path` 后旧链接还能用吗？**
A：不能。旧链接路径不再匹配路由，需重新生成分享链接；修改后请清除路由缓存。

**Q：报表数据为空？**
A：检查 `track_enabled` 是否开启；事件表 `create_time` 是否在所选时间范围内；趋势图读日聚合表，若 `aggregate_enabled` 曾关闭，可调用 `TrackService::aggregate($date)` 重算。

**Q：如何复用已有的聚码/访问统计应用？**
A：本应用不设强依赖。若要把中转链接换成 `cmspro.qrlink` 短链，在业务应用或本应用的扩展点挂 `share.link.build` 过滤器调用其 `QrlinkService::createCode()`；来源分析可把 `share.event.recorded` 事件转发给 `cmspro.sitestat`。

**Q：后台列表表头与数据列没有对齐？**
A：按《应用视图规范》7.5 节处理。本应用后台与用户端所有 Layui 表格均满足三点：至少保留一列 `minWidth` 弹性列（不设 `width`）、设置 `skin: false` 统一 `th`/`td` 盒模型、用完整 `page` 配置对象替代 `page: true` 简写；每张表显式声明 `id`，数据看板三张表只在初始化时 `render` 一次，查询时用 `table.reload` 更新，避免叠加多套表头容器。回归用例见 `Tests/Feature/AdminTableViewTest.php`。若浏览器仍显示旧布局，请先强制刷新清掉缓存的旧视图/CSS。

> 注意：本应用 `links` / `mine` / `invites` 接口读取的分页参数名是 `limit`，不是框架后台列表的 `per_page` 约定，改造列表时不要照搬 `request.limitName` 映射。

---

## 八、版本历史

| 版本 | 日期 | 说明 |
|------|------|------|
| 1.0.0 | 2026-11-05 | 初始版本 |
