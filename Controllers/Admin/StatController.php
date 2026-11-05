<?php

namespace App\Apps\CmsproShare\Controllers\Admin;

use App\Apps\CmsproShare\Services\ShareConfig;
use App\Apps\CmsproShare\Services\StatService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

/**
 * 后台 · 数据看板
 */
class StatController extends Controller
{
    public function __construct(
        private StatService $stats,
        private ShareConfig $config
    ) {
    }

    public function index()
    {
        return view('cmspro.share::Admin.stats.index', [
            'defaultDays' => $this->config->int('report_default_days', 30),
        ]);
    }

    public function apiStats(Request $request)
    {
        $days = max(1, min(365, $this->config->int('report_default_days', 30)));
        $end = (string) $request->input('end', now()->toDateString());
        $start = (string) $request->input('start', now()->subDays($days - 1)->toDateString());

        return ApiResponse::success([
            'range' => ['start' => $start, 'end' => $end],
            'overview' => $this->stats->overview($start, $end),
            'by_channel' => $this->stats->byChannel($start, $end),
            'by_scene' => $this->stats->byScene($start, $end),
            'trend' => $this->stats->trend($start, $end),
            'top_links' => $this->stats->topLinks($start, $end),
        ]);
    }
}
