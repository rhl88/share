-- ============================================================================
-- 数据库变更脚本：一键分享应用 v1.0.0 初始化
-- ============================================================================
-- 版本号: V1.0.0
-- 变更描述: 新建渠道、场景、分享链接、跟踪事件、裂变关系、日聚合六张表
-- 影响表: app_cmspro_share_channels / scenes / links / events / invites / daily_stats
-- 变更类型: DDL（结构变更，幂等）
-- 应用ID: cmspro.share
-- 创建日期: 2026-11-05
-- 预计执行时间: < 1 秒（六张空表）
-- 风险等级: 低（全部为新增表，不修改任何既有表）
-- 说明: 正常安装/升级由应用 Migrations/ 自动执行；本脚本用于人工部署、审计与回滚参考。
--       表前缀 app_cmspro_share_ 符合「应用数据库隔离」规范。
-- ============================================================================

-- ---------- 1. 渠道表 ----------
CREATE TABLE IF NOT EXISTS `app_cmspro_share_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `code` varchar(64) NOT NULL COMMENT '渠道标识，全局唯一',
  `name` varchar(100) NOT NULL COMMENT '渠道显示名称',
  `region` varchar(20) NOT NULL DEFAULT 'domestic' COMMENT '归属区域：domestic-国内 overseas-海外 common-通用',
  `mode` varchar(32) NOT NULL DEFAULT 'link' COMMENT '分享方式：link/copy/qrcode/poster/native/wechat_sdk',
  `template` text NULL COMMENT '链接模板，支持 {url} {title} {summary} {image} 占位符',
  `icon` varchar(100) NULL COMMENT '图标类名或图标地址',
  `sort` int unsigned NOT NULL DEFAULT 0 COMMENT '展示排序',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态：0-禁用 1-启用',
  `is_builtin` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '是否内置渠道：0-自定义 1-内置',
  `create_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` timestamp NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_share_channels_code` (`code`),
  KEY `idx_share_channels_region` (`region`),
  KEY `idx_share_channels_status` (`status`),
  KEY `idx_share_channels_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='一键分享渠道表';

-- ---------- 2. 场景注册表 ----------
CREATE TABLE IF NOT EXISTS `app_cmspro_share_scenes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `code` varchar(100) NOT NULL COMMENT '分享场景标识，如 blog.article',
  `name` varchar(100) NOT NULL COMMENT '场景显示名称',
  `app_id` varchar(100) NOT NULL DEFAULT '' COMMENT '注册该场景的应用ID',
  `resolver` varchar(255) NULL COMMENT '元数据解析器，格式 类名::方法',
  `url_template` text NULL COMMENT '内容原链接模板，支持 {id} 占位符',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态：0-禁用 1-启用',
  `sort` int unsigned NOT NULL DEFAULT 0 COMMENT '展示排序',
  `create_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` timestamp NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_share_scenes_code` (`code`),
  KEY `idx_share_scenes_app_id` (`app_id`),
  KEY `idx_share_scenes_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='一键分享场景注册表';

-- ---------- 3. 分享链接表 ----------
CREATE TABLE IF NOT EXISTS `app_cmspro_share_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `code` varchar(32) NOT NULL COMMENT '分享短码，中转链接唯一标识',
  `scene_code` varchar(100) NOT NULL COMMENT '分享场景标识',
  `target_id` varchar(64) NOT NULL COMMENT '分享对象ID',
  `target_url` text NOT NULL COMMENT '内容原始链接',
  `title` varchar(255) NULL COMMENT '分享标题',
  `summary` varchar(500) NULL COMMENT '分享摘要',
  `image` varchar(500) NULL COMMENT '分享缩略图地址',
  `channel_code` varchar(64) NULL COMMENT '生成时指定的渠道标识',
  `sharer_id` bigint unsigned NULL COMMENT '分享者ID，关联 users 表',
  `sharer_type` varchar(20) NOT NULL DEFAULT 'guest' COMMENT '分享者类型：guest/user/admin',
  `parent_code` varchar(32) NULL COMMENT '上级分享短码，用于裂变关系链',
  `params` text NULL COMMENT '扩展参数，JSON 格式',
  `expire_time` datetime NULL COMMENT '链接失效时间，null 表示长期有效',
  `click_count` int unsigned NOT NULL DEFAULT 0 COMMENT '分享点击次数',
  `visit_count` int unsigned NOT NULL DEFAULT 0 COMMENT '落地访问次数',
  `uv_count` int unsigned NOT NULL DEFAULT 0 COMMENT '落地独立访客数',
  `convert_count` int unsigned NOT NULL DEFAULT 0 COMMENT '转化次数',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态：0-停用 1-启用',
  `create_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` timestamp NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_share_links_code` (`code`),
  KEY `idx_share_links_scene_target` (`scene_code`,`target_id`),
  KEY `idx_share_links_sharer_id` (`sharer_id`),
  KEY `idx_share_links_parent_code` (`parent_code`),
  KEY `idx_share_links_create_time` (`create_time`),
  KEY `idx_share_links_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='一键分享链接表';

-- ---------- 4. 跟踪事件表 ----------
CREATE TABLE IF NOT EXISTS `app_cmspro_share_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `link_id` bigint unsigned NULL COMMENT '分享链接ID',
  `link_code` varchar(32) NOT NULL COMMENT '分享短码',
  `scene_code` varchar(100) NOT NULL DEFAULT '' COMMENT '分享场景标识',
  `target_id` varchar(64) NULL COMMENT '分享对象ID',
  `channel_code` varchar(64) NULL COMMENT '渠道标识',
  `event_type` varchar(24) NOT NULL COMMENT '事件类型：create/click/landing/convert',
  `sharer_id` bigint unsigned NULL COMMENT '分享者ID',
  `visitor_key` varchar(64) NULL COMMENT '访客标识，md5(ip|ua|日期)',
  `ip` varchar(64) NULL COMMENT '访客IP',
  `user_agent` varchar(500) NULL COMMENT '访客UA',
  `device` varchar(20) NULL COMMENT '设备类型：mobile/desktop/tablet',
  `referer` varchar(500) NULL COMMENT '来源地址',
  `extra` text NULL COMMENT '扩展数据，JSON 格式',
  `create_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_share_events_link_code` (`link_code`),
  KEY `idx_share_events_event_type` (`event_type`),
  KEY `idx_share_events_visitor_key` (`visitor_key`),
  KEY `idx_share_events_create_time` (`create_time`),
  KEY `idx_share_events_scene_type_time` (`scene_code`,`event_type`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='一键分享跟踪事件表';

-- ---------- 5. 裂变邀请关系表 ----------
CREATE TABLE IF NOT EXISTS `app_cmspro_share_invites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `link_code` varchar(32) NOT NULL COMMENT '本次分享短码',
  `parent_code` varchar(32) NULL COMMENT '上级分享短码',
  `inviter_id` bigint unsigned NULL COMMENT '邀请人ID，关联 users 表',
  `visitor_key` varchar(64) NOT NULL COMMENT '被邀请访客标识',
  `visitor_id` bigint unsigned NULL COMMENT '访客登录后绑定的用户ID',
  `level` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '关系层级：1-直接 2-二级 3-三级',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态：0-失效 1-有效',
  `create_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` timestamp NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_share_invites_link_visitor` (`link_code`,`visitor_key`),
  KEY `idx_share_invites_inviter_id` (`inviter_id`),
  KEY `idx_share_invites_visitor_id` (`visitor_id`),
  KEY `idx_share_invites_parent_code` (`parent_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='一键分享裂变邀请关系表';

-- ---------- 6. 日聚合统计表 ----------
CREATE TABLE IF NOT EXISTS `app_cmspro_share_daily_stats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `stat_date` date NOT NULL COMMENT '统计日期',
  `scene_code` varchar(100) NOT NULL DEFAULT '' COMMENT '分享场景标识，空表示全部',
  `channel_code` varchar(64) NOT NULL DEFAULT '' COMMENT '渠道标识，空表示全部',
  `event_type` varchar(24) NOT NULL COMMENT '事件类型：create/click/landing/convert',
  `cnt` int unsigned NOT NULL DEFAULT 0 COMMENT '事件发生次数',
  `uv` int unsigned NOT NULL DEFAULT 0 COMMENT '独立访客数',
  `create_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `update_time` timestamp NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_share_daily` (`stat_date`,`scene_code`,`channel_code`,`event_type`),
  KEY `idx_share_daily_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='一键分享日聚合统计表';

-- ---------- 数据验证 ----------
SELECT TABLE_NAME, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'app_cmspro_share_%';

-- ---------- 回滚方案（仅在确认无业务数据后执行，不可逆） ----------
-- DROP TABLE IF EXISTS `app_cmspro_share_daily_stats`;
-- DROP TABLE IF EXISTS `app_cmspro_share_invites`;
-- DROP TABLE IF EXISTS `app_cmspro_share_events`;
-- DROP TABLE IF EXISTS `app_cmspro_share_links`;
-- DROP TABLE IF EXISTS `app_cmspro_share_scenes`;
-- DROP TABLE IF EXISTS `app_cmspro_share_channels`;
-- 说明：正常卸载由应用 Install::uninstall() 调用迁移 down() 逆序回滚，
--       禁止在应用代码中用 Schema::dropIfExists 绕过 down()。
