<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_share_invites')) {
            return;
        }

        Schema::create('app_cmspro_share_invites', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('link_code', 32)->comment('本次分享短码，关联 app_cmspro_share_links.code');
            $table->string('parent_code', 32)->nullable()->comment('上级分享短码，构成裂变关系链');
            $table->unsignedBigInteger('inviter_id')->nullable()->comment('邀请人ID，关联 users 表');
            $table->string('visitor_key', 64)->comment('被邀请访客标识');
            $table->unsignedBigInteger('visitor_id')->nullable()->comment('访客登录后绑定的用户ID');
            $table->unsignedTinyInteger('level')->default(1)->comment('关系层级：1-直接邀请 2-二级 3-三级');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0-失效 1-有效');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->unique(['link_code', 'visitor_key'], 'uniq_share_invites_link_visitor');
            $table->index('inviter_id');
            $table->index('visitor_id');
            $table->index('parent_code');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_cmspro_share_invites` COMMENT '一键分享裂变邀请关系表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_share_invites');
    }
};
