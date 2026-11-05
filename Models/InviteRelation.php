<?php

namespace App\Apps\CmsproShare\Models;

use App\Models\BaseModel;

/**
 * 裂变邀请关系模型
 */
class InviteRelation extends BaseModel
{
    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    protected $table = 'app_cmspro_share_invites';

    protected $fillable = [
        'link_code', 'parent_code', 'inviter_id', 'visitor_key',
        'visitor_id', 'level', 'status',
    ];

    protected $casts = [
        'inviter_id' => 'integer',
        'visitor_id' => 'integer',
        'level' => 'integer',
        'status' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];
}
