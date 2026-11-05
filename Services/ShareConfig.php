<?php

namespace App\Apps\CmsproShare\Services;

use App\Models\ConfigItem;

/**
 * 一键分享配置读取服务
 *
 * 优先级：config_items 表（后台设置） > Config/share.php（默认值） > 调用方兜底值。
 * 单例绑定，请求内缓存；表不存在或值为空时回退默认值，保证未安装完成也不报错。
 */
class ShareConfig
{
    private const CODE_PREFIX = 'app_cmspro_share_';

    private const CONFIG_KEY = 'apps.cmspro.share.';

    /** @var array<string,mixed> 请求内缓存 */
    private array $cache = [];

    /**
     * @param mixed $default 兜底值
     * @return mixed
     */
    public function get(string $name, $default = null)
    {
        if (array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }

        $value = null;
        try {
            $value = ConfigItem::where('code', self::CODE_PREFIX . $name)->value('value');
        } catch (\Throwable $e) {
            // 配置表尚未建立（安装前 / 精简测试环境）→ 回退默认配置
        }

        if ($value === null || $value === '') {
            $value = config(self::CONFIG_KEY . $name, $default);
        }

        return $this->cache[$name] = $value;
    }

    public function bool(string $name, bool $default = false): bool
    {
        $value = $this->get($name, $default ? '1' : '0');
        return $value === '1' || $value === 1 || $value === true || $value === 'true';
    }

    public function int(string $name, int $default = 0): int
    {
        return (int) $this->get($name, (string) $default);
    }

    public function string(string $name, string $default = ''): string
    {
        return (string) $this->get($name, $default);
    }

    /**
     * 清空请求内缓存（后台保存配置后调用）
     */
    public function flush(): void
    {
        $this->cache = [];
    }
}
