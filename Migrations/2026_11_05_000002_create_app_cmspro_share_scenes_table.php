<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_share_scenes')) {
            return;
        }

        Schema::create('app_cmspro_share_scenes', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('code', 100)->unique()->comment('分享场景标识，如 blog.article，由业务应用注册');
            $table->string('name', 100)->comment('场景显示名称');
            $table->string('app_id', 100)->default('')->comment('注册该场景的应用ID');
            $table->string('resolver', 255)->nullable()->comment('元数据解析器，格式 类名::方法 或 应用提供的回调标识');
            $table->text('url_template')->nullable()->comment('内容原链接模板，支持 {id} 占位符');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0-禁用 1-启用（后台可覆盖钩子注册值）');
            $table->unsignedInteger('sort')->default(0)->comment('展示排序');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->index('app_id');
            $table->index('status');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_cmspro_share_scenes` COMMENT '一键分享场景注册表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_share_scenes');
    }
};
