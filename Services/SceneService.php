<?php

namespace App\Apps\CmsproShare\Services;

use App\Apps\CmsproShare\Models\Scene;
use App\Services\HookManager;
use Illuminate\Support\Facades\Schema;

/**
 * 分享场景（内容类型）注册表
 *
 * 场景完全由业务应用通过 share.types 钩子注册，本应用不内置任何业务类型。
 * 每个场景提供元数据解析器，把「类型 + 对象ID」解析为标题 / 摘要 / 图片 / 原链接。
 * 数据库表仅保存后台的启用、排序覆盖配置，以及钩子注册场景的快照（供后台列表展示）。
 */
class SceneService
{
    /**
     * 获取全部已注册场景
     *
     * @return array<string,array<string,mixed>> 以场景 code 为键
     */
    public function all(): array
    {
        $scenes = app(HookManager::class)->applyFilter('share.types', []);
        if (!is_array($scenes)) {
            $scenes = [];
        }

        foreach ($scenes as $code => $scene) {
            if (!is_array($scene)) {
                unset($scenes[$code]);
                continue;
            }
            $scenes[$code] = array_merge([
                'code' => $code,
                'name' => $code,
                'app_id' => '',
                'status' => 1,
                'sort' => 0,
            ], $scene);
        }

        // 后台覆盖：禁用 / 排序 / 手动登记的场景
        if (Schema::hasTable('app_cmspro_share_scenes')) {
            foreach (Scene::query()->get() as $row) {
                $code = (string) $row->code;
                $existing = $scenes[$code] ?? [
                    'code' => $code,
                    'name' => $row->name,
                    'app_id' => $row->app_id,
                    'resolver' => $row->resolver,
                    'url_template' => $row->url_template,
                ];
                $existing['status'] = (int) $row->status;
                $existing['sort'] = (int) $row->sort;
                $scenes[$code] = $existing;
            }
        }

        return $scenes;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get(string $code): ?array
    {
        $scene = $this->all()[$code] ?? null;
        if ($scene === null || (int) ($scene['status'] ?? 1) !== 1) {
            return null;
        }

        return $scene;
    }

    /**
     * 解析分享上下文
     *
     * 解析器支持：闭包、[类名, 方法名]、'类名::方法名' 字符串；
     * 未配置解析器时用 url_template 兜底生成原链接。
     * 解析结果再经 share.context 钩子改写，供其他应用注入自定义文案。
     *
     * @param string     $code     场景标识
     * @param string|int $targetId 分享对象ID
     * @return array<string,mixed> title / summary / image / url / scene_code / target_id
     */
    public function resolve(string $code, $targetId): array
    {
        $scene = $this->get($code) ?? [];
        $meta = $this->callResolver($scene['resolver'] ?? null, $code, $targetId);

        if (!is_array($meta)) {
            $meta = [];
        }

        $template = (string) ($scene['url_template'] ?? '');
        $fallbackUrl = $template !== ''
            ? str_replace('{id}', (string) $targetId, $template)
            : '';

        $context = [
            'scene_code' => $code,
            'scene_name' => (string) ($scene['name'] ?? $code),
            'target_id' => (string) $targetId,
            'title' => (string) ($meta['title'] ?? ''),
            'summary' => (string) ($meta['summary'] ?? ''),
            'image' => (string) ($meta['image'] ?? ''),
            'url' => (string) ($meta['url'] ?? $fallbackUrl),
        ];

        $filtered = app(HookManager::class)->applyFilter('share.context', $context);

        return is_array($filtered) ? $filtered : $context;
    }

    /**
     * 把钩子注册的场景同步写入数据库，供后台场景管理列表展示
     *
     * 仅保存可序列化字段，闭包形式的解析器不落库（运行时仍由钩子提供）。
     */
    public function syncFromHooks(): int
    {
        if (!Schema::hasTable('app_cmspro_share_scenes')) {
            return 0;
        }

        $count = 0;
        foreach ($this->all() as $code => $scene) {
            $resolver = $scene['resolver'] ?? null;
            $serialized = is_string($resolver) ? $resolver
                : (is_array($resolver) && isset($resolver[0], $resolver[1]) && is_string($resolver[0])
                    ? $resolver[0] . '::' . $resolver[1]
                    : null);

            $row = Scene::where('code', $code)->first();
            $payload = [
                'name' => (string) ($scene['name'] ?? $code),
                'app_id' => (string) ($scene['app_id'] ?? ''),
                'resolver' => $serialized,
                'url_template' => (string) ($scene['url_template'] ?? ''),
            ];

            if ($row) {
                $row->update($payload);
            } else {
                Scene::create(array_merge($payload, [
                    'code' => $code,
                    'status' => 1,
                    'sort' => 0,
                ]));
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param mixed $resolver
     * @return mixed
     */
    private function callResolver($resolver, string $code, $targetId)
    {
        if ($resolver instanceof \Closure) {
            return $resolver($targetId, $code);
        }

        if (is_array($resolver) && count($resolver) === 2 && is_callable($resolver)) {
            return call_user_func($resolver, $targetId, $code);
        }

        if (is_string($resolver) && str_contains($resolver, '::')) {
            [$class, $method] = explode('::', $resolver, 2);
            if (class_exists($class) && is_callable([$class, $method])) {
                return call_user_func([app($class), $method], $targetId, $code);
            }
        }

        return [];
    }
}
