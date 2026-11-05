<?php

namespace App\Apps\CmsproShare\Models;

use App\Models\BaseModel;

/**
 * 分享日聚合统计模型
 */
class DailyStat extends BaseModel
{
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    protected $table = 'app_cmspro_share_daily_stats';

    protected $fillable = [
        'stat_date', 'scene_code', 'channel_code', 'event_type', 'cnt', 'uv',
    ];

    protected $casts = [
        'cnt' => 'integer',
        'uv' => 'integer',
        'stat_date' => 'date:Y-m-d',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];
}
