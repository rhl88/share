<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_share_channels')) {
            return;
        }

        Schema::create('app_cmspro_share_channels', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('code', 64)->unique()->comment('渠道标识，全局唯一');
            $table->string('name', 100)->comment('渠道显示名称');
            $table->string('region', 20)->default('domestic')->comment('归属区域：domestic-国内 overseas-海外 common-通用');
            $table->string('mode', 32)->default('link')->comment('分享方式：link-链接跳转 copy-复制链接 qrcode-二维码 poster-海报 native-系统分享 wechat_sdk-微信SDK');
            $table->text('template')->nullable()->comment('链接模板，支持 {url} {title} {summary} {image} 占位符');
            $table->string('icon', 100)->nullable()->comment('图标类名或图标地址');
            $table->unsignedInteger('sort')->default(0)->comment('展示排序，数值越小越靠前');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0-禁用 1-启用');
            $table->unsignedTinyInteger('is_builtin')->default(0)->comment('是否内置渠道：0-自定义 1-内置');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->index('region');
            $table->index('status');
            $table->index('sort');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_cmspro_share_channels` COMMENT '一键分享渠道表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_share_channels');
    }
};
