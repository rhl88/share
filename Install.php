<?php

namespace App\Apps\CmsproShare;

use App\Apps\CmsproShare\Services\ChannelService;
use App\Models\ConfigGroup;
use App\Models\ConfigItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 一键分享应用安装 / 卸载 / 升级脚本
 *
 * 安装：迁移兜底 → 配置三向同步 → 内置渠道种子
 * 卸载：配置清理 → 迁移逆序回滚兜底 → 缓存清理（禁止手动删表，表由 down() 负责）
 * 升级：迁移兜底 → 配置三向同步 → 内置渠道种子（幂等，不覆盖用户调整）
 */
class Install
{
    private const APP_ID = 'cmspro.share';

    public function install(): void
    {
        $this->runMigrations();
        $this->seedDefaultConfigs();
        $this->seedDefaultChannels();
    }

    public function uninstall(): void
    {
        $this->removeConfigs();
        $this->rollbackMigrations();
        Cache::flush();
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        $this->runMigrations();
        $this->seedDefaultConfigs();
        $this->seedDefaultChannels();
    }

    /**
     * 迁移执行兜底：Windows 等环境下系统迁移可能未执行，此处直接调用 up()
     */
    protected function runMigrations(): void
    {
        foreach (glob(__DIR__ . '/Migrations/*.php') ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            if (DB::table('migrations')->where('migration', $name)->exists()) {
                continue;
            }

            $migration = require $file;
            if (!method_exists($migration, 'up')) {
                continue;
            }

            try {
                $migration->up();
                DB::table('migrations')->insert([
                    'migration' => $name,
                    'batch' => (int) DB::table('migrations')->max('batch') + 1,
                ]);
            } catch (\Throwable $e) {
                Log::error('一键分享应用迁移执行失败', [
                    'file' => basename($file),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 卸载迁移回滚兜底：逆序调用 down() 并删除 migrations 记录
     */
    protected function rollbackMigrations(): void
    {
        foreach (array_reverse(glob(__DIR__ . '/Migrations/*.php') ?: []) as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            if (!DB::table('migrations')->where('migration', $name)->exists()) {
                continue;
            }

            $migration = require $file;
            if (!method_exists($migration, 'down')) {
                continue;
            }

            try {
                $migration->down();
                DB::table('migrations')->where('migration', $name)->delete();
            } catch (\Throwable $e) {
                Log::error('一键分享应用迁移回滚失败', [
                    'file' => basename($file),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 配置三向同步（以 manifest.json 为唯一基准）
     *
     * 新增写入默认值；已有仅更新结构、保留用户设置 value；已移除的清理 DB 残留。
     */
    protected function seedDefaultConfigs(): void
    {
        if (!Schema::hasTable('config_groups') || !Schema::hasTable('config_items')) {
            return;
        }

        $manifestPath = __DIR__ . '/manifest.json';
        $manifest = is_file($manifestPath)
            ? json_decode((string) file_get_contents($manifestPath), true)
            : null;

        if (!is_array($manifest)) {
            Log::error('一键分享应用配置同步失败：manifest.json 解析失败');
            return;
        }

        $manifestCodes = [];

        foreach ($manifest['config_groups'] ?? [] as $groupDef) {
            $group = ConfigGroup::updateOrCreate(
                ['code' => $this->buildGroupCode($groupDef['name'])],
                ['name' => $groupDef['title'], 'app_id' => self::APP_ID, 'sort' => 0, 'status' => 1]
            );

            foreach ($groupDef['items'] as $index => $item) {
                $code = $this->buildItemCode($item['name']);
                $manifestCodes[] = $code;
                $this->upsertConfigItem($group->id, $item, $code, $index);
            }
        }

        // 清理 manifest 中已删除的配置项与空配置组
        ConfigItem::where('code', 'like', $this->prefix() . '%')
            ->whereNotIn('code', $manifestCodes)
            ->delete();
        ConfigGroup::where('app_id', self::APP_ID)->whereDoesntHave('items')->delete();
    }

    /**
     * @param array<string,mixed> $item
     */
    protected function upsertConfigItem(int $groupId, array $item, string $code, int $sort): void
    {
        $options = !empty($item['options'])
            ? json_encode($item['options'], JSON_UNESCAPED_UNICODE)
            : null;

        $existing = ConfigItem::where('code', $code)->first();

        if ($existing) {
            $existing->update([
                'group_id' => $groupId,
                'name' => $item['title'],
                'type' => $item['type'] ?? 'text',
                'options' => $options,
                'tips' => $item['tips'] ?? '',
                'sort' => $sort,
                'status' => 1,
            ]);
            return;
        }

        ConfigItem::create([
            'code' => $code,
            'group_id' => $groupId,
            'name' => $item['title'],
            'type' => $item['type'] ?? 'text',
            'value' => $item['value'] ?? '',
            'options' => $options,
            'tips' => $item['tips'] ?? '',
            'sort' => $sort,
            'status' => 1,
        ]);
    }

    /**
     * 写入内置渠道种子（幂等，不覆盖后台已调整的渠道）
     */
    protected function seedDefaultChannels(): void
    {
        if (!Schema::hasTable('app_cmspro_share_channels')) {
            return;
        }

        foreach ((new ChannelService())->defaults() as $channel) {
            if (DB::table('app_cmspro_share_channels')->where('code', $channel['code'])->exists()) {
                continue;
            }

            DB::table('app_cmspro_share_channels')->insert([
                'code' => $channel['code'],
                'name' => $channel['name'],
                'region' => $channel['region'],
                'mode' => $channel['mode'],
                'template' => $channel['template'],
                'icon' => $channel['icon'],
                'sort' => $channel['sort'],
                'status' => 1,
                'is_builtin' => 1,
                'create_time' => now(),
                'update_time' => now(),
            ]);
        }
    }

    protected function removeConfigs(): void
    {
        ConfigItem::where('code', 'like', $this->prefix() . '%')->delete();
        ConfigGroup::where('code', 'like', $this->prefix() . '%')->delete();
    }

    protected function prefix(): string
    {
        return 'app_' . str_replace('.', '_', self::APP_ID) . '_';
    }

    protected function buildGroupCode(string $name): string
    {
        return $this->prefix() . $name;
    }

    protected function buildItemCode(string $name): string
    {
        return $this->prefix() . $name;
    }
}
