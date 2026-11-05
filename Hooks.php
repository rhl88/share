<?php

namespace App\Apps\CmsproShare;

use App\Apps\CmsproShare\Models\ShareEvent;
use App\Services\HookManager;
use Illuminate\Support\Facades\Log;

/**
 * 一键分享应用钩子注册器
 *
 * 本应用向其他应用**暴露**以下扩展点（在 Service 内触发，故此处不注册监听）：
 * - share.types        Filter：注册分享场景（内容类型）及元数据解析器
 * - share.channels     Filter：追加或改写分享渠道
 * - share.context      Filter：改写分享文案与元数据
 * - share.link.build   Filter：改写最终分享链接（可挂短链 / UTM）
 * - share.event.recorded Action：事件落库后通知
 * - share.converted    Action：转化归因成功后通知奖励系统
 *
 * 本类只负责挂载系统级钩子，注册时第 4 参数必须传 app_id，
 * 以便应用禁用/卸载时系统自动摘除。
 */
class Hooks
{
    private const APP_ID = 'cmspro.share';

    public function register(HookManager $hooks): void
    {
        $hooks->registerFilter('dashboard.stats', [$this, 'filterDashboardStats'], 10, self::APP_ID);
    }

    /**
     * 向系统仪表盘注入今日分享数据
     *
     * 查询异常（应用表未安装等）时原样返回，绝不影响系统统计接口可用性。
     *
     * @param array<string,mixed> $stats 系统已汇总的统计数据
     * @return array<string,mixed>
     */
    public function filterDashboardStats(array $stats): array
    {
        try {
            $today = now()->toDateString();
            $stats['share_today_created'] = ShareEvent::where('event_type', 'create')
                ->whereDate('create_time', $today)->count();
            $stats['share_today_landing'] = ShareEvent::where('event_type', 'landing')
                ->whereDate('create_time', $today)->count();
        } catch (\Throwable $e) {
            Log::info('[cmspro.share][hook] dashboard.stats 统计注入失败：' . $e->getMessage());
        }

        return $stats;
    }
}
