<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_share_links')) {
            return;
        }

        Schema::create('app_cmspro_share_links', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('code', 32)->unique()->comment('分享短码，中转链接唯一标识');
            $table->string('scene_code', 100)->comment('分享场景标识，关联 app_cmspro_share_scenes.code');
            $table->string('target_id', 64)->comment('分享对象ID，由业务应用解释');
            $table->text('target_url')->comment('内容原始链接，中转后跳转目标');
            $table->string('title', 255)->nullable()->comment('分享标题');
            $table->string('summary', 500)->nullable()->comment('分享摘要');
            $table->string('image', 500)->nullable()->comment('分享缩略图地址');
            $table->string('channel_code', 64)->nullable()->comment('生成时指定的渠道标识');
            $table->unsignedBigInteger('sharer_id')->nullable()->comment('分享者ID，关联 users 表');
            $table->string('sharer_type', 20)->default('guest')->comment('分享者类型：guest-游客 user-前台用户 admin-管理员');
            $table->string('parent_code', 32)->nullable()->comment('上级分享短码，用于裂变关系链');
            $table->text('params')->nullable()->comment('扩展参数，JSON 格式，如 UTM 参数');
            $table->dateTime('expire_time')->nullable()->comment('链接失效时间，null 表示长期有效');
            $table->unsignedInteger('click_count')->default(0)->comment('分享点击次数');
            $table->unsignedInteger('visit_count')->default(0)->comment('落地访问次数');
            $table->unsignedInteger('uv_count')->default(0)->comment('落地独立访客数');
            $table->unsignedInteger('convert_count')->default(0)->comment('转化次数');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0-停用 1-启用');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->index(['scene_code', 'target_id'], 'idx_share_links_scene_target');
            $table->index('sharer_id');
            $table->index('parent_code');
            $table->index('create_time');
            $table->index('status');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_cmspro_share_links` COMMENT '一键分享链接表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_share_links');
    }
};
