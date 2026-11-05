<?php

namespace App\Apps\CmsproShare;

use App\Apps\CmsproShare\Services\ShareConfig;
use App\Models\ConfigItem;
use App\Services\HookManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * 一键分享应用 · 服务提供者
 *
 * 负责：应用配置合并、辅助函数加载、三端路由注册、视图命名空间注册、钩子注册。
 * 本应用不修改任何框架文件，所有能力通过应用自身代码声明。
 */
class ServiceProvider extends BaseServiceProvider
{
    private const APP_PATH = 'app/Apps/CmsproShare';

    private const APP_ID = 'cmspro.share';

    private const CONFIG_KEY = 'apps.cmspro.share';

    private const VIEW_NAMESPACE = 'cmspro.share';

    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path(self::APP_PATH . '/Config/share.php'),
            self::CONFIG_KEY
        );

        require_once base_path(self::APP_PATH . '/helpers.php');

        // 配置读取在单次请求内共享缓存，保存配置后由 ShareConfig::flush() 清空
        $this->app->singleton(ShareConfig::class);
    }

    public function boot(): void
    {
        $this->registerRoutes();
        $this->loadViews();
        $this->registerHooks();
    }

    /**
     * 三端路由注册
     *
     * 后台 /api/admin/cmspro/share，用户端 /user/cmspro/share，
     * 前台中转 /{access_path}/{code}，前台公开接口 /api/cmspro/share。
     */
    protected function registerRoutes(): void
    {
        Route::prefix('admin/cmspro/share')
            ->namespace('App\Apps\CmsproShare\Controllers\Admin')
            ->middleware(['web', 'auth:admin'])
            ->group(base_path(self::APP_PATH . '/Routes/admin.php'));

        Route::prefix('api/admin/cmspro/share')
            ->namespace('App\Apps\CmsproShare\Controllers\Admin')
            ->middleware(['web', 'auth:admin'])
            ->group(base_path(self::APP_PATH . '/Routes/admin_api.php'));

        Route::prefix('user/cmspro/share')
            ->namespace('App\Apps\CmsproShare\Controllers\User')
            ->middleware(['web', 'auth:web'])
            ->group(base_path(self::APP_PATH . '/Routes/user.php'));

        Route::prefix('api/user/cmspro/share')
            ->namespace('App\Apps\CmsproShare\Controllers\User')
            ->middleware(['web', 'auth:web'])
            ->group(base_path(self::APP_PATH . '/Routes/user_api.php'));

        Route::prefix($this->getAccessPath())
            ->namespace('App\Apps\CmsproShare\Controllers\Home')
            ->middleware(['web', 'throttle:60,1'])
            ->group(base_path(self::APP_PATH . '/Routes/home.php'));

        Route::prefix('api/cmspro/share')
            ->namespace('App\Apps\CmsproShare\Controllers\Api')
            ->middleware(['throttle:120,1'])
            ->group(base_path(self::APP_PATH . '/Routes/home_api.php'));
    }

    protected function loadViews(): void
    {
        $this->loadViewsFrom(
            base_path(self::APP_PATH . '/Views'),
            self::VIEW_NAMESPACE
        );
    }

    protected function registerHooks(): void
    {
        $manifestPath = base_path(self::APP_PATH . '/manifest.json');
        $manifest = is_file($manifestPath)
            ? json_decode((string) file_get_contents($manifestPath), true)
            : null;

        $hooksClass = is_array($manifest) && isset($manifest['hooks'])
            ? 'App\\Apps\\CmsproShare\\' . $manifest['hooks']
            : null;

        if ($hooksClass !== null && class_exists($hooksClass)) {
            (new $hooksClass)->register(app(HookManager::class));
        }
    }

    /**
     * 读取中转路径前缀
     *
     * boot 阶段数据库已就绪，但仍做异常兜底：配置表不存在时回退默认值，
     * 保证应用未安装完成或测试环境下框架仍能启动。
     */
    protected function getAccessPath(): string
    {
        try {
            $value = ConfigItem::where('code', 'app_cmspro_share_access_path')->value('value');
            if (is_string($value) && $value !== '') {
                return trim($value, '/');
            }
        } catch (\Throwable $e) {
            // 配置表尚未建立时回退到内存配置
        }

        return (string) config(self::CONFIG_KEY . '.access_path', 'share');
    }
}
