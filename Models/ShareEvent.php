<?php

namespace App\Apps\CmsproShare\Models;

use App\Models\BaseModel;

/**
 * 分享跟踪事件模型
 */
class ShareEvent extends BaseModel
{
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'create_time';

    protected $table = 'app_cmspro_share_events';

    protected $fillable = [
        'link_id', 'link_code', 'scene_code', 'target_id', 'channel_code',
        'event_type', 'sharer_id', 'visitor_key', 'ip', 'user_agent',
        'device', 'referer', 'extra', 'create_time',
    ];

    protected $casts = [
        'link_id' => 'integer',
        'sharer_id' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
    ];
}
