<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_share_events')) {
            return;
        }

        Schema::create('app_cmspro_share_events', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('link_id')->nullable()->comment('分享链接ID，关联 app_cmspro_share_links.id');
            $table->string('link_code', 32)->comment('分享短码');
            $table->string('scene_code', 100)->default('')->comment('分享场景标识');
            $table->string('target_id', 64)->nullable()->comment('分享对象ID');
            $table->string('channel_code', 64)->nullable()->comment('渠道标识');
            $table->string('event_type', 24)->comment('事件类型：create-生成 click-点击 landing-落地 convert-转化');
            $table->unsignedBigInteger('sharer_id')->nullable()->comment('分享者ID');
            $table->string('visitor_key', 64)->nullable()->comment('访客标识，md5(ip|ua|日期)');
            $table->string('ip', 64)->nullable()->comment('访客IP');
            $table->string('user_agent', 500)->nullable()->comment('访客UA');
            $table->string('device', 20)->nullable()->comment('设备类型：mobile-移动端 desktop-桌面端 tablet-平板');
            $table->string('referer', 500)->nullable()->comment('来源地址');
            $table->text('extra')->nullable()->comment('扩展数据，JSON 格式');
            $table->timestamp('create_time')->nullable()->comment('创建时间');

            $table->index('link_code');
            $table->index('event_type');
            $table->index('visitor_key');
            $table->index('create_time');
            $table->index(['scene_code', 'event_type', 'create_time'], 'idx_share_events_scene_type_time');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_cmspro_share_events` COMMENT '一键分享跟踪事件表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_share_events');
    }
};
