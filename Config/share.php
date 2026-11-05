<?php

/**
 * 一键分享应用默认配置
 *
 * 与 manifest.json 的 config_groups 保持一致。运行期优先读取 config_items 表中的用户设置，
 * 未安装或未配置时回退到本文件（ServiceProvider 合并到 apps.cmspro.share）。
 */
return [
    'access_path' => 'share',
    'default_channels' => 'wechat,moments,qq,qzone,weibo,copy,qrcode,facebook,x,whatsapp,telegram',
    'link_ttl_days' => '0',
    'code_length' => '8',
    'track_enabled' => '1',
    'data_retention_days' => '90',
    'default_title' => '',
    'default_image' => '',

    'wechat_sdk_enabled' => '0',
    'wechat_app_id' => '',
    'wechat_app_secret' => '',
    'qrcode_enabled' => '1',
    'poster_enabled' => '0',
    'native_enabled' => '1',
    'copy_enabled' => '1',
    'wechat_guide_text' => '请点击右上角菜单，选择「发送给朋友」或「分享到朋友圈」',

    'invite_enabled' => '1',
    'invite_max_level' => '2',
    'invite_window_days' => '30',
    'convert_enabled' => '1',
    'reward_event_enabled' => '1',
    'parent_param' => 'from',

    'uv_dedup_enabled' => '1',
    'utm_enabled' => '1',
    'utm_source' => 'share',
    'aggregate_enabled' => '1',
    'report_default_days' => '30',
];
