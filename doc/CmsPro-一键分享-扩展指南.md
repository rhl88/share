# CmsPro 一键分享扩展指南

> 版本：1.0.0 | 更新日期：2026-11-05
> 应用版本：1.0.0 | 应用 ID：`cmspro.share`
> 适用应用：CmsproShare v1.0.0+

---

## 一、概述

本文档面向 CmsPro 应用开发者，说明**任意应用如何接入一键分享中台**。

设计原则：**分享中台不认识任何业务内容**。业务应用通过 `share.types` 钩子注册自己的内容类型（场景）及元数据解析器，再在模板里调用一行 helper 即可获得完整分享面板。新增业务类型、新增渠道都不需要修改本应用代码。

扩展接口共三类：

| 类别 | 内容 |
|------|------|
| Filter 钩子 | `share.types`、`share.channels`、`share.context`、`share.link.build` |
| Action 钩子 | `share.event.recorded`、`share.converted` |
| 代码接口 | `ShareService` 等 8 个服务类、4 个全局 helper、6 个模型、6 个公开 HTTP 接口 |

**命名空间**：`App\Apps\CmsproShare\`

---

## 二、前置检查

本应用**零强依赖**，可独立安装。业务应用如需声明依赖，可在自己的 `manifest.json` 中写：

```json
{
    "dependencies": {
        "app_ids": ["cmspro.share"],
        "versions": {}
    }
}
```

不做强声明时，建议运行时降级检查：

```php
if (!function_exists('share_render') || !share_is_installed()) {
    // 一键分享未启用：隐藏分享按钮，不影响自身业务
    return '';
}
```

> `share_is_installed()` 查询 `apps` 表判断应用是否安装且启用，异常时返回 `false`。

---

## 三、快速接入（两步）

### 步骤 1：在业务应用的 `Hooks.php` 注册场景

```php
<?php

namespace App\Apps\CmsproBlog;

use App\Services\HookManager;

class Hooks
{
    private const APP_ID = 'cmspro.blog';   // 必须是本应用自己的 app_id

    public function register(HookManager $hooks): void
    {
        $hooks->registerFilter('share.types', [$this, 'registerShareTypes'], 10, self::APP_ID);
    }

    /**
     * @param array<string,array<string,mixed>> $types
     * @return array<string,array<string,mixed>>
     */
    public function registerShareTypes(array $types): array
    {
        $types['blog.article'] = [
            'code'         => 'blog.article',              // 全局唯一，建议 {app}.{实体}
            'name'         => '博客文章',
            'app_id'       => self::APP_ID,
            'resolver'     => [ArticleShareResolver::class, 'resolve'], // 元数据解析器
            'url_template' => '/article/{id}',             // 兜底原链接，支持 {id}
        ];

        return $types;
    }
}
```

`resolver` 支持三种形式：

| 形式 | 示例 | 说明 |
|------|------|------|
| 闭包 | `function ($targetId, $sceneCode) { return [...]; }` | 适合简单场景，不落库 |
| 类方法数组 | `[ArticleShareResolver::class, 'resolve']` | 推荐，可落库快照 |
| 类名::方法 | `'App\Apps\CmsproBlog\Services\ArticleShareResolver::resolve'` | 适合后台手动登记 |

解析器返回结构（所有键均可省略，缺省取 `url_template` 与兜底配置）：

```php
<?php

namespace App\Apps\CmsproBlog\Services;

class ArticleShareResolver
{
    /**
     * @param string|int $targetId 分享对象ID
     * @param string     $sceneCode 场景标识
     * @return array{title:string,summary:string,image:string,url:string}
     */
    public function resolve($targetId, string $sceneCode): array
    {
        $article = Article::find($targetId);

        return [
            'title'   => $article->title,
            'summary' => mb_substr(strip_tags($article->content), 0, 100),
            'image'   => $article->cover,
            'url'     => url('/article/' . $article->id),  // 内容原始链接
        ];
    }
}
```

### 步骤 2：在模板里渲染分享面板

```blade
{{-- 最简：一行接入 --}}
{!! share_render('blog.article', $article->id) !!}

{{-- 指定分享者、上级分享码（裂变）、自定义样式 --}}
{!! share_render('forum.post', $post->id, [
    'sharer_id'    => auth('web')->id(),
    'parent_code'  => request()->query('from'),
    'show_label'   => false,
    'button_class' => 'my-theme-btn',
]) !!}
```

前端交互（复制链接、二维码弹层、海报、原生分享、微信引导、点击上报）由自动注入的 `Assets/js/share.js` 接管，业务页面无需额外代码。

> 分享者 ID 未显式传入时，服务会自动取 `auth('web')->id()`；上级分享码未传入时，自动读取当前请求中 `parent_param`（默认 `from`）参数。

---

## 四、纯前端接入（JS SDK）

不改动 Blade 模板、由前端动态渲染时使用：

```html
<link rel="stylesheet" href="/apps/cmspro.share/css/share.css">
<div class="cmspro-share" data-share-widget="/api/cmspro/share/widget/AbCd1234"></div>
<script src="/apps/cmspro.share/js/share.js" defer></script>
```

> 资源目录按 `public/apps/{appId}/` 发布，本应用 app_id 为 `cmspro.share`，**目录名必须保留点号**。写成 `/apps/cmspro/share/` 会命中 Laravel 回退页（返回 `text/html`），浏览器将拒绝加载样式表，按钮会退化成无样式的原生按钮。也可直接用 `share_asset('css/share.css')` 生成地址。
>
> 按钮图标使用 FontAwesome 4.7 类名（渠道 `icon` 字段），业务前台页面需按《CMSPRO-UI开发规范》引入 `CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css`；后台视图已统一引入。`share.css` 会按 `data-channel` 为图标着色，未匹配到品牌色的自定义渠道回退灰蓝色。

也可先调用 `ShareService::build()` 取得短码，再交给前端：

```php
$result = app(\App\Apps\CmsproShare\Services\ShareService::class)
    ->build('shop.goods', $goodsId, ['sharer_id' => $userId]);

$code = $result['data']['code'];   // 传给前端容器 data-share-widget
```

`GET /api/cmspro/share/widget/{code}` 返回 `code / url / title / summary / image / channels[] / sdk`，SDK 会自动渲染按钮。

---

## 五、钩子扩展点

### 5.1 `share.types` · Filter · 注册分享场景

- **触发位置**：`SceneService::all()`
- **参数**：`$types`（以场景 code 为键的关联数组）
- **返回**：必须返回数组
- **场景字段**：`code` / `name` / `app_id` / `resolver` / `url_template` / `status` / `sort`

后台「场景管理」可对钩子注册的场景做启停与排序覆盖（写 `app_cmspro_share_scenes` 表），但不会修改业务应用代码。

### 5.2 `share.channels` · Filter · 注册或改写渠道

```php
$hooks->registerFilter('share.channels', function (array $channels): array {
    $channels['my_app'] = [
        'code'     => 'my_app',
        'name'     => '我的 App',
        'region'   => 'common',                 // domestic / overseas / common
        'mode'     => 'link',                   // link / copy / qrcode / poster / native / wechat_sdk
        'icon'     => 'fa fa-paper-plane',
        'sort'     => 99,
        'status'   => 1,
        'template' => 'myapp://share?url={url}&title={title}',
    ];

    // 也可改写内置渠道
    $channels['weibo']['name'] = '微博分享';

    return $channels;
}, 10, 'cmspro.blog');
```

- **占位符**：`{url}` `{title}` `{summary}` `{image}`，均做 `rawurlencode` 编码
- **非 `link` 模式**：`template` 被忽略，前端使用中转链接本身
- **渠道白名单**：后台「默认展示渠道」只约束内置渠道，钩子注册的自定义渠道始终展示（除非 `status=0`）
- **能力开关**：`qrcode/poster/native/copy` 四种 mode 受对应后台开关控制

### 5.3 `share.context` · Filter · 改写分享文案

```php
$hooks->registerFilter('share.context', function (array $context): array {
    // $context: scene_code / scene_name / target_id / title / summary / image / url
    $context['title'] = '【推荐】' . $context['title'];
    return $context;
}, 10, 'cmspro.blog');
```

适合统一加品牌前缀、替换图片域名、按渠道注入参数等。

### 5.4 `share.link.build` · Filter · 改写最终分享链接

```php
$hooks->registerFilter('share.link.build', function (string $url, array $context): string {
    // 复用聚码应用生成短链（可选联动，非强依赖）
    if (!class_exists(\App\Apps\CmsproQrlink\Services\QrlinkService::class)) {
        return $url;   // 未安装时优雅降级
    }

    $qrCode = app(\App\Apps\CmsproQrlink\Services\QrlinkService::class)
        ->createCode(['title' => $context['title'], 'target_url' => $url]);

    // createCode 返回 QrCode 模型，用聚码提供的 helper 转成完整短链
    return function_exists('qrlink_short_url') ? qrlink_short_url($qrCode->code) : $url;
}, 10, 'cmspro.blog');
```

- **参数**：`$url`（中转链接，可能已带 UTM）、`$context`（含 `code` / `title` / `scene_code` 等）
- **返回**：字符串；返回空串时服务自动回退到原中转链接
- 可用于挂外部短链、CDN 域名、自定义归因参数

### 5.5 `share.event.recorded` · Action · 事件通知

```php
$hooks->registerAction('share.event.recorded', function (array $event): void {
    // $event: link_code / event_type / scene_code / channel_code / visitor_key / sharer_id ...
    // 例：把落地事件转发给 cmspro.sitestat 做站外来源分析
}, 10, 'cmspro.blog');
```

事件类型：`create`（链接生成）、`click`（渠道点击）、`landing`（中转落地）、`convert`（转化）。

### 5.6 `share.converted` · Action · 转化归因通知（奖励入口）

```php
$hooks->registerAction('share.converted', function (array $payload): void {
    // $payload: link_code / scene_code / target_id / sharer_id / user_id / event / extra
    // 由积分、分销等业务应用自行发放奖励；本应用不内置结算逻辑
}, 10, 'cmspro.blog');
```

> 该事件仅在 `convert_enabled` 与 `reward_event_enabled` 均开启时触发。奖励规则属于业务，请勿在本应用内实现。

### 5.7 钩子注册约定

```php
$hooks->registerFilter('share.types', $callback, 10, '你的应用app_id');
$hooks->registerAction('share.converted', $callback, 10, '你的应用app_id');
```

**第 4 个参数必须是业务应用自己的 app_id**：应用禁用/卸载时系统据此自动摘除其钩子，避免调用到不存在的类。

---

## 六、Service 层调用

所有服务通过容器解析：`app(\App\Apps\CmsproShare\Services\ShareService::class)`。

### 6.1 ShareService（主入口）

| 方法 | 签名 | 说明 |
|------|------|------|
| `build` | `build(string $sceneCode, string\|int $targetId, array $options = []): array` | 生成分享链接与渠道列表，写 `links` + `create` 事件 |
| `url` | `url(string $sceneCode, $targetId, array $options = []): string` | 只返回中转链接，失败返回 `''` |
| `render` | `render(string $sceneCode, $targetId, array $options = []): string` | 渲染面板 HTML |
| `findByCode` | `findByCode(string $code): ?ShareLink` | 按短码查链接（含过期校验） |
| `landing` | `landing(string $code, Request $request): ?string` | 落地处理，返回目标 URL 或 null |
| `widget` | `widget(string $code): array` | 纯 JS SDK 所需数据 |
| `convert` | `convert(string $code, string $event, array $extra = []): array` | 转化上报与归因 |

`build()` / `convert()` 返回统一结构：

```php
[
    'success' => true,
    'message' => 'ok',
    'data'    => [ /* 失败时为 null */ ],
]
```

`build()` 的 `data` 关键字段：`code`、`url`（中转链接）、`target_url`、`title`、`summary`、`image`、`scene`、`channels[]`、`sdk`。

`options` 可选键：`sharer_id`、`sharer_type`、`parent_code`、`channel`、`params`（数组，存入 `links.params`）。

**转化上报示例（业务应用注册成功后）**：

```php
use App\Apps\CmsproShare\Services\ShareService;

$shareCode = session('share_code');   // 由业务侧在落地页自行保存
if ($shareCode) {
    app(ShareService::class)->convert($shareCode, 'register', [
        'user_id'  => $user->id,
        'order_no' => '',
    ]);
}
```

也可直接调 HTTP 接口：`POST /api/cmspro/share/convert`，参数 `code` / `event` / 任意业务扩展字段。

### 6.2 ChannelService

| 方法 | 签名 | 说明 |
|------|------|------|
| `defaults` | `defaults(): array` | 22 个内置渠道定义 |
| `all` | `all(): array` | 合并内置 + DB 覆盖 + 钩子后按 sort 排序的启用渠道 |
| `get` | `get(string $code): ?array` | 取单个渠道 |
| `buildUrl` | `buildUrl(array $channel, array $context): string` | 按模板生成分享链接 |
| `saveOverride` | `saveOverride(string $code, array $data): void` | 后台保存启停/排序/模板 |
| `seedDefaults` | `seedDefaults(): int` | 幂等写入内置渠道种子 |

### 6.3 SceneService

| 方法 | 签名 | 说明 |
|------|------|------|
| `all` | `all(): array` | 钩子注册场景 + 后台覆盖 |
| `get` | `get(string $code): ?array` | 取单个启用场景 |
| `resolve` | `resolve(string $code, $targetId): array` | 解析分享上下文（经 `share.context` 过滤） |
| `syncFromHooks` | `syncFromHooks(): int` | 把注册场景快照写入 DB |

### 6.4 TrackService

| 方法 | 签名 | 说明 |
|------|------|------|
| `record` | `record(string $linkCode, string $eventType, ?Request $request = null, array $extra = []): ?ShareEvent` | 记录事件、更新计数、日聚合、触发通知 |
| `visitorKey` | `visitorKey(Request $request): string` | `md5(ip\|UA\|日期)` |
| `device` | `device(Request $request): string` | mobile / tablet / desktop |
| `bindInvite` | `bindInvite(ShareLink $link, Request $request, ?int $userId = null): ?InviteRelation` | 绑定裂变关系 |
| `aggregate` | `aggregate(string $date): void` | 按日重算聚合（覆盖式，可重复执行） |
| `cleanExpired` | `cleanExpired(int $days): int` | 清理过期原始事件 |

### 6.5 StatService

| 方法 | 签名 | 说明 |
|------|------|------|
| `funnel` | `funnel(string $start, string $end, array $filters = []): array` | 生成/点击/落地/转化 + 点击率/转化率 |
| `overview` | `overview(string $start, string $end): array` | 看板总览（含链接数、UV、邀请数） |
| `byChannel` | `byChannel(string $start, string $end): array` | 按渠道统计 |
| `byScene` | `byScene(string $start, string $end): array` | 按场景统计 |
| `trend` | `trend(string $start, string $end): array` | 趋势（读日聚合表） |
| `topLinks` | `topLinks(string $start, string $end, int $limit = 10): array` | 热门分享链接 |

日期参数统一 `Y-m-d` 字符串。

### 6.6 其他服务

| 服务 | 主要方法 | 说明 |
|------|---------|------|
| `ShareConfig` | `get` / `bool` / `int` / `string` / `flush` | 配置读取（DB > 默认配置 > 兜底） |
| `WechatService` | `isConfigured` / `signature(string $url): array` | 微信 JS-SDK 签名，未配置返回 `configured=false` |
| `PosterService` | `qrcode(string $data, int $size): string` / `poster(string $code): string` | 返回 SVG 字符串 |

---

## 七、辅助函数

| 函数 | 签名 | 说明 |
|------|------|------|
| `share_render` | `share_render(string $sceneCode, $targetId, array $options = []): string` | 渲染完整分享面板 HTML（含 SDK/样式注入，单次请求只注入一次） |
| `share_url` | `share_url(string $sceneCode, $targetId, array $options = []): string` | 返回中转链接，失败返回 `''` |
| `share_config` | `share_config(string $name, $default = null)` | 读取配置项（name 不含前缀） |
| `share_asset` | `share_asset(string $path): string` | 生成 `public/apps/cmspro.share/` 下的组件资源地址 |
| `share_is_installed` | `share_is_installed(): bool` | 应用是否已安装并启用 |

> 所有函数均以 `function_exists` 包裹，不会与其他应用冲突；失败时返回空串或 HTML 注释，不抛异常、不破坏页面布局。

---

## 八、模型引用

模型继承 `App\Models\BaseModel`，命名空间 `App\Apps\CmsproShare\Models`，日期字段经 `$casts` 统一输出 `Y-m-d H:i:s`（禁止二次 `format()`）。

| 模型 | 数据表 | 主要字段 |
|------|--------|---------|
| `Channel` | `app_cmspro_share_channels` | code / name / region / mode / template / icon / sort / status / is_builtin |
| `Scene` | `app_cmspro_share_scenes` | code / name / app_id / resolver / url_template / status / sort |
| `ShareLink` | `app_cmspro_share_links` | code / scene_code / target_id / target_url / title / summary / image / channel_code / sharer_id / sharer_type / parent_code / params / expire_time / click_count / visit_count / uv_count / convert_count / status |
| `ShareEvent` | `app_cmspro_share_events` | link_code / event_type / channel_code / visitor_key / ip / user_agent / device / extra / create_time |
| `InviteRelation` | `app_cmspro_share_invites` | link_code / parent_code / inviter_id / visitor_key / visitor_id / level / status |
| `DailyStat` | `app_cmspro_share_daily_stats` | stat_date / scene_code / channel_code / event_type / cnt / uv |

`ShareLink::paramsArray()` 返回解码后的扩展参数数组；`Channel::scopeEnabled()` 可只查启用渠道。

---

## 九、与现有应用联动（可选）

| 目标 | 接入点 | 做法 |
|------|--------|------|
| 用 `cmspro.qrlink` 生成站外短链 | `share.link.build` | 调用 `QrlinkService::createCode()`，未安装时原样返回 |
| 把落地来源写入 `cmspro.sitestat` | `share.event.recorded` | 监听 `landing` 事件后调用其 `CollectService::collect()` |
| 注册/下单后发积分或佣金 | `share.converted` | 监听事件，按 `sharer_id` + `event` 自行结算并做幂等 |
| 追加自有渠道 | `share.channels` | 注册带 `template` 的渠道定义 |
| 改写分享标题/图片 | `share.context` | 统一加品牌前缀、替换 CDN 域名 |

所有联动均为**可选**：业务应用先 `class_exists` / `share_is_installed()` 判断，未安装时降级，不得因此阻断主流程。

---

## 十、注意事项

1. **钩子归属**：注册 Filter/Action 时第 4 参数必须传业务应用自己的 app_id，禁用/卸载后系统自动摘除钩子。
2. **数据隔离**：本应用六张表统一 `app_cmspro_share_` 前缀；业务应用不要直接写这些表，优先用 Service 或 HTTP 接口。
3. **日期格式**：统一 `Y-m-d H:i:s`，报表日期参数用 `Y-m-d`；禁止 ISO 8601，禁止对模型日期二次 `format()`。
4. **降级优先**：一键分享未安装/未启用时，业务应用应隐藏分享入口而不抛异常；`share_render()` 已内置异常兜底。
5. **配置缓存**：`ShareConfig` 为请求内单例；若业务应用直接写 `config_items`，保存后调用 `ShareConfig::flush()`。
6. **奖励幂等**：`share.converted` 可能因重复上报被多次触发，奖励发放侧必须自行按 `link_code + event + user_id` 做幂等。
7. **中转链接语义**：`share.link.build` 改写的是对外分享的中转链接；落地仍跳转到 `links.target_url`，不会给业务页面附加额外参数。UTM 参数位于中转链接上。
8. **链接有效期**：`link_ttl_days > 0` 时过期链接不再跳转，展示友好提示页；长期分享场景建议设为 0。
9. **微信签名 URL**：必须是当前页面完整地址（不含 `#`），SPA 路由变化后需重新取签名。
10. **测试落点**：业务应用的接入测试放自身 `Tests/`，用 `php artisan test app/Apps/{AppName}/Tests` 运行，禁止放入框架 `tests/`。

---

## 十一、版本与更新日志

| 版本 | 日期 | 更新人 | 说明 |
|------|------|--------|------|
| 1.0.0 | 2026-11-05 | 开发团队 | 初始版本：4 个 Filter + 2 个 Action 扩展点、8 个服务、5 个 helper、6 个模型、6 个公开接口 |
