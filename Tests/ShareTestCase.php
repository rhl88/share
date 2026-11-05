<?php

namespace App\Apps\CmsproShare\Tests;

use App\Apps\CmsproShare\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 一键分享应用测试基类
 *
 * 应用未安装时系统不会自动加载其 Provider，因此测试基类需：
 * 1. 补建系统表（config_items / apps / users）与应用六张业务表（直接执行迁移 up()，保证与生产一致）
 * 2. 手动注册应用 ServiceProvider，加载三端路由、视图与钩子
 * 3. 每个用例前清空应用数据，保证用例间隔离
 */
abstract class ShareTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createCoreTables();
        $this->createAppTables();
        $this->truncateAppData();

        config(['apps.cmspro.share.access_path' => 'share']);

        $this->app->register(ServiceProvider::class);
        app('router')->getRoutes()->refreshNameLookups();
    }

    protected function createCoreTables(): void
    {
        if (!Schema::hasTable('config_items')) {
            Schema::create('config_items', function ($table) {
                $table->bigIncrements('id');
                $table->string('code', 100)->unique();
                $table->unsignedBigInteger('group_id')->default(0);
                $table->string('name', 100)->nullable();
                $table->string('type', 30)->default('text');
                $table->text('value')->nullable();
                $table->text('options')->nullable();
                $table->text('tips')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->unsignedTinyInteger('status')->default(1);
                $table->timestamp('create_time')->nullable();
                $table->timestamp('update_time')->nullable();
            });
        }

        if (!Schema::hasTable('apps')) {
            Schema::create('apps', function ($table) {
                $table->bigIncrements('id');
                $table->string('app_id', 64)->unique();
                $table->string('name', 100)->nullable();
                $table->string('version', 30)->default('');
                $table->unsignedTinyInteger('status')->default(1);
                $table->timestamp('create_time')->nullable();
                $table->timestamp('update_time')->nullable();
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->bigIncrements('id');
                $table->string('username', 50)->nullable();
                $table->string('password', 255)->nullable();
                $table->string('nickname', 50)->nullable();
                $table->unsignedTinyInteger('status')->default(1);
                $table->timestamp('create_time')->nullable();
                $table->timestamp('update_time')->nullable();
            });
        }
    }

    protected function createAppTables(): void
    {
        $files = glob(__DIR__ . '/../Migrations/*.php');
        foreach ($files as $file) {
            $migration = require $file;
            $migration->up();
        }
    }

    protected function truncateAppData(): void
    {
        foreach ([
            'app_cmspro_share_channels',
            'app_cmspro_share_scenes',
            'app_cmspro_share_links',
            'app_cmspro_share_events',
            'app_cmspro_share_invites',
            'app_cmspro_share_daily_stats',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    /**
     * 写入一个配置项（模拟后台设置）
     */
    protected function setShareConfig(string $name, string $value): void
    {
        DB::table('config_items')->updateOrInsert(
            ['code' => 'app_cmspro_share_' . $name],
            ['value' => $value, 'name' => $name, 'type' => 'text', 'status' => 1]
        );

        // 配置服务为请求内单例，写入后需清空缓存，保证后续请求读到新值
        app(\App\Apps\CmsproShare\Services\ShareConfig::class)->flush();
    }

    /**
     * 模拟业务应用注册一个分享场景
     */
    protected function registerScene(string $code = 'blog.article', array $meta = []): void
    {
        $default = [
            'code' => $code,
            'name' => '博客文章',
            'app_id' => 'test.blog',
            'url_template' => 'https://example.com/article/{id}',
            'resolver' => function ($targetId) {
                return [
                    'title' => '文章标题-' . $targetId,
                    'summary' => '文章摘要',
                    'image' => 'https://example.com/cover.jpg',
                    'url' => 'https://example.com/article/' . $targetId,
                ];
            },
        ];

        app(\App\Services\HookManager::class)->registerFilter(
            'share.types',
            function ($types) use ($default, $meta) {
                $types[$default['code']] = array_merge($default, $meta);
                return $types;
            },
            10,
            'test.blog'
        );
    }
}
