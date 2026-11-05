# 一键分享 应用文档

| 项目 | 内容 |
|------|------|
| 应用 ID | `cmspro.share` |
| 应用名称 | 一键分享 |
| 应用版本 | 1.0.0 |
| 文档版本 | 1.0.0 |
| 更新日期 | 2026-11-05 |
| 运行依赖 | PHP >= 8.3、CmsPro >= 5.0.0 |
| 应用依赖 | 无（可独立安装；可选联动 `cmspro.qrlink`、`cmspro.sitestat`） |
| 官方地址 | https://www.cmspro.cn/apps/cmspro.share |

---

## 应用概述

「一键分享」是 CMSPRO v5 的通用分享中台。它不绑定任何业务内容类型，由业务应用通过钩子注册分享场景，本应用统一负责渠道管理、链接生成、中转跳转、裂变邀请归因与全链路数据跟踪，形成「一次配置、任意应用复用」的业务闭环：

**业务应用注册场景 → 生成带追踪参数的分享链接 → 国内外主流渠道分发 → 中转落地记录与邀请绑定 → 转化归因与奖励通知 → 数据看板复盘**

核心能力：

- 22 个内置渠道：国内（微信、QQ、微博、抖音、小红书、钉钉等）+ 海外（Facebook、X、LinkedIn、WhatsApp、Telegram、Pinterest、Reddit、LINE、邮件）+ 通用（复制、二维码、海报、系统原生分享）
- 4 个 Filter 扩展点 + 2 个 Action 扩展点，任意应用可注册内容类型、追加渠道、改写文案与链接、监听转化
- 裂变关系链（1-3 级）、归因窗口、转化上报与奖励事件，奖励规则由业务应用自行实现
- 生成 → 点击 → 落地 → 转化四段漏斗、UV 去重、UTM 参数、日聚合与多维报表
- 微信 JS-SDK 签名、SVG 二维码与海报（零 GD 依赖）、全部能力由后台 27 项配置自由开关

## 目录结构

```
CmsproShare/
├── manifest.json                 # 应用元数据：菜单 / 配置 / 权限 / 前台路由声明
├── ServiceProvider.php           # 三端路由、视图命名空间、配置合并、helpers 与钩子注册
├── Hooks.php                     # 监听系统 dashboard.stats 注入分享统计
├── Install.php                   # 安装 / 卸载 / 升级、迁移兜底、配置三向同步、渠道种子
├── helpers.php                   # share_render / share_url / share_config / share_is_installed
├── icon.svg                      # 应用图标
├── Config/share.php              # 默认配置（与 manifest 对齐）
├── Routes/                       # admin / admin_api / user / user_api / home / home_api
├── Controllers/                  # Admin（5）/ User / Home / Api
├── Services/                     # ShareConfig / Channel / Scene / Share / Track / Stat / Wechat / Poster
├── Models/                       # 6 张表对应模型
├── Migrations/                   # 6 个幂等建表迁移
├── Views/                        # Admin（含 preview）/ User / Home 三端 Blade 视图
├── Assets/                       # js/share.js 前端 SDK、css/share.css
├── Tests/                        # 应用级测试（45 个用例 / 324 断言）
├── sql/                          # V1.0.0__init_share_tables.sql 建表与回滚脚本
└── doc/                          # 三类应用文档
```

## 应用文档

- [特性清单](doc/CmsPro-一键分享-特性清单.md) —— 框架特性覆盖情况与实现位置
- [使用指南](doc/CmsPro-一键分享-使用指南.md) —— 安装、配置、功能验证、卸载与 FAQ
- [扩展指南](doc/CmsPro-一键分享-扩展指南.md) —— 其他应用如何注册场景、追加渠道、上报转化

## 快速接入

```php
// 1. 业务应用 Hooks.php 注册场景
$hooks->registerFilter('share.types', function (array $types): array {
    $types['blog.article'] = [
        'code' => 'blog.article', 'name' => '博客文章',
        'app_id' => 'cmspro.blog',
        'resolver' => [ArticleShareResolver::class, 'resolve'],
        'url_template' => '/article/{id}',
    ];
    return $types;
}, 10, 'cmspro.blog');
```

```blade
{{-- 2. 模板里一行渲染 --}}
{!! share_render('blog.article', $article->id) !!}
```

详见[扩展指南](doc/CmsPro-一键分享-扩展指南.md)。

## 验证

```bash
# 在 code 目录执行（自动锁定 sqlite :memory:）
php artisan test app/Apps/CmsproShare/Tests
# 结果：45 passed (324 assertions)
```
