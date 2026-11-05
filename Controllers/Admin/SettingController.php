<?php

namespace App\Apps\CmsproShare\Controllers\Admin;

use App\Apps\CmsproShare\Services\ShareConfig;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ConfigItem;
use Illuminate\Http\Request;

/**
 * 后台 · 应用设置
 *
 * 只接受 manifest.json / Config/share.php 中声明的配置项，避免任意键写入。
 */
class SettingController extends Controller
{
    private const CODE_PREFIX = 'app_cmspro_share_';

    public function __construct(private ShareConfig $config)
    {
    }

    public function index()
    {
        return view('cmspro.share::Admin.settings.index');
    }

    public function apiList()
    {
        $items = [];
        $meta = $this->configMeta();

        foreach ($this->allowedKeys() as $key => $default) {
            $definition = $meta[$key] ?? [];
            $items[] = [
                'code' => $key,
                'name' => (string) ($definition['title'] ?? $key),
                'group' => (string) ($definition['group'] ?? '基础设置'),
                'type' => (string) ($definition['type'] ?? 'text'),
                'tips' => (string) ($definition['tips'] ?? ''),
                'options' => $definition['options'] ?? [],
                'value' => (string) $this->config->get($key, $default),
                'default' => (string) $default,
            ];
        }

        return ApiResponse::success($items);
    }

    /**
     * 从 manifest.json 读取配置项的中文标题、分组、类型与提示
     *
     * @return array<string,array<string,mixed>>
     */
    private function configMeta(): array
    {
        $path = base_path('app/Apps/CmsproShare/manifest.json');
        $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!is_array($manifest)) {
            return [];
        }

        $meta = [];
        foreach ($manifest['config_groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $meta[$item['name']] = array_merge($item, ['group' => $group['title']]);
            }
        }

        return $meta;
    }

    public function apiSave(Request $request)
    {
        $input = $request->input('settings', []);
        if (!is_array($input)) {
            return ApiResponse::error(422, 'settings 必须为对象');
        }

        $allowed = $this->allowedKeys();
        $saved = 0;

        foreach ($input as $key => $value) {
            if (!array_key_exists($key, $allowed) || is_array($value)) {
                continue;
            }

            ConfigItem::updateOrCreate(
                ['code' => self::CODE_PREFIX . $key],
                ['value' => (string) $value, 'status' => 1]
            );
            $saved++;
        }

        $this->config->flush();

        return ApiResponse::success(['saved' => $saved], '设置已保存');
    }

    /**
     * @return array<string,mixed>
     */
    private function allowedKeys(): array
    {
        $defaults = config('apps.cmspro.share');
        if (!is_array($defaults)) {
            $defaults = require base_path('app/Apps/CmsproShare/Config/share.php');
        }

        return $defaults;
    }
}
