<?php

namespace App\Apps\CmsproShare\Models;

use App\Models\BaseModel;

/**
 * 分享链接模型
 */
class ShareLink extends BaseModel
{
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    protected $table = 'app_cmspro_share_links';

    protected $fillable = [
        'code', 'scene_code', 'target_id', 'target_url', 'title', 'summary',
        'image', 'channel_code', 'sharer_id', 'sharer_type', 'parent_code',
        'params', 'expire_time', 'click_count', 'visit_count', 'uv_count',
        'convert_count', 'status',
    ];

    protected $casts = [
        'sharer_id' => 'integer',
        'click_count' => 'integer',
        'visit_count' => 'integer',
        'uv_count' => 'integer',
        'convert_count' => 'integer',
        'status' => 'integer',
        'expire_time' => 'datetime:Y-m-d H:i:s',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 扩展参数数组
     *
     * @return array<string,mixed>
     */
    public function paramsArray(): array
    {
        $decoded = json_decode((string) $this->params, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }
}
