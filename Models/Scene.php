<?php

namespace App\Apps\CmsproShare\Models;

use App\Models\BaseModel;

/**
 * 分享场景（内容类型）模型
 *
 * 场景由业务应用通过 share.types 钩子注册，本表仅保存后台的启用/排序覆盖配置。
 */
class Scene extends BaseModel
{
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    protected $table = 'app_cmspro_share_scenes';

    protected $fillable = [
        'code', 'name', 'app_id', 'resolver', 'url_template', 'status', 'sort',
    ];

    protected $casts = [
        'status' => 'integer',
        'sort' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];
}
