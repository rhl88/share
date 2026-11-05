<?php

namespace App\Apps\CmsproShare\Models;

use App\Models\BaseModel;

/**
 * 分享渠道模型
 */
class Channel extends BaseModel
{
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    protected $table = 'app_cmspro_share_channels';

    protected $fillable = [
        'code', 'name', 'region', 'mode', 'template', 'icon',
        'sort', 'status', 'is_builtin',
    ];

    protected $casts = [
        'sort' => 'integer',
        'status' => 'integer',
        'is_builtin' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }
}
