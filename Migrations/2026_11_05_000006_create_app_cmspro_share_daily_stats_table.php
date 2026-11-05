<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_share_daily_stats')) {
            return;
        }

        Schema::create('app_cmspro_share_daily_stats', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->date('stat_date')->comment('统计日期');
            $table->string('scene_code', 100)->default('')->comment('分享场景标识，空表示全部');
            $table->string('channel_code', 64)->default('')->comment('渠道标识，空表示全部');
            $table->string('event_type', 24)->comment('事件类型：create/click/landing/convert');
            $table->unsignedInteger('cnt')->default(0)->comment('事件发生次数');
            $table->unsignedInteger('uv')->default(0)->comment('独立访客数');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->unique(['stat_date', 'scene_code', 'channel_code', 'event_type'], 'uniq_share_daily');
            $table->index('stat_date');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_cmspro_share_daily_stats` COMMENT '一键分享日聚合统计表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_share_daily_stats');
    }
};
