<?php
/**
 * Copyright (c) Since 2023 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Funnlink\Pluck;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Funnlink\Pluck\Plugin\Manager;

class PluginServiceProvider extends ServiceProvider
{
    private string $pluginBasePath = '';

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('plugin', function () {
            return new Manager();
        });
    }

    /**
     * Bootstrap Plugin Service Provider
     *
     * @throws \Exception
     */
    public function boot(): void
    {
        $manager              = app('plugin');
        $this->pluginBasePath = base_path('plugins');

        $allPlugins = $manager->getPlugins();
        foreach ($allPlugins as $plugin) {
            $pluginCode = $plugin->getDirname();
            $this->loadMigrations($pluginCode);
            $this->loadViews($pluginCode);
            $this->loadTranslations($pluginCode);
        }

        $enabledPlugins = $manager->getEnabledPlugins();
        foreach ($enabledPlugins as $plugin) {
            $pluginCode = $plugin->getDirname();
            $this->bootPlugin($plugin);
            $this->registerRoutes($pluginCode);
            $this->registerMiddleware($pluginCode);
            $this->loadDesignComponents($pluginCode);
        }
    }

    /**
     * Boot plugins using Bootstrap::boot()
     *
     * @param $plugin
     */
    private function bootPlugin($plugin): void
    {
        $filePath   = $plugin->getBootFile();
        $pluginCode = $plugin->getDirname();
        if (file_exists($filePath)) {
            $className = "Plugin\\{$pluginCode}\\Bootstrap";
            if (method_exists($className, 'boot')) {
                (new $className)->boot();
            }
        }
    }

    /**
     * 加载插件数据库迁移脚本
     *
     * @param $pluginCode
     */
    private function loadMigrations($pluginCode): void
    {
        $migrationPath = "{$this->pluginBasePath}/{$pluginCode}/Migrations";
        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom("{$migrationPath}");
        }
    }

    /**
     * Load and register for admin and shop
     *
     * @param $pluginCode
     */
    private function registerRoutes($pluginCode): void
    {
        $this->registerAdminRoutes($pluginCode);
        $this->registerShopRoutes($pluginCode);
    }

    /**
     * Register admin routes
     *
     * @param $pluginCode
     */
    private function registerAdminRoutes($pluginCode): void
    {
        $pluginBasePath = $this->pluginBasePath;
        $adminRoutePath = "{$pluginBasePath}/{$pluginCode}/Routes/admin.php";
        if (file_exists($adminRoutePath)) {
            $adminName = admin_name();
            Route::prefix($adminName)
                ->name("{$adminName}.")
                ->middleware(['admin', 'admin_auth:admin'])
                ->group(function () use ($adminRoutePath) {
                    $this->loadRoutesFrom($adminRoutePath);
                });
        }
    }

    /**
     * Register shop routes
     *
     * @param $pluginCode
     */
    private function registerShopRoutes($pluginCode): void
    {
        $pluginBasePath = $this->pluginBasePath;
        $shopRoutePath  = "{$pluginBasePath}/{$pluginCode}/Routes/shop.php";
        if (file_exists($shopRoutePath)) {
            Route::name('shop.')
                ->middleware('shop')
                ->group(function () use ($shopRoutePath) {
                    $this->loadRoutesFrom($shopRoutePath);
                });
        }
    }

    /**
     * Load translations
     */
    private function loadTranslations($pluginCode): void
    {
        $pluginBasePath = $this->pluginBasePath;
        $this->loadTranslationsFrom("{$pluginBasePath}/{$pluginCode}/Lang", $pluginCode);
    }

    /**
     * Load view template
     *
     * @param $pluginCode
     */
    private function loadViews($pluginCode): void
    {
        $pluginBasePath = $this->pluginBasePath;
        $this->loadViewsFrom("{$pluginBasePath}/{$pluginCode}/Views", $pluginCode);
    }

    /**
     * Register middleware from plugins
     */
    private function registerMiddleware($pluginCode): void
    {
        $pluginBasePath = $this->pluginBasePath;
        $middlewarePath = "{$pluginBasePath}/{$pluginCode}/Middleware";

        $router           = $this->app['router'];
        $shopMiddlewares  = $this->loadMiddlewares("$middlewarePath/Shop");
        $adminMiddlewares = $this->loadMiddlewares("$middlewarePath/Admin");

        if ($shopMiddlewares) {
            foreach ($shopMiddlewares as $shopMiddleware) {
                $router->pushMiddlewareToGroup('shop', $shopMiddleware);
            }
        }

        if ($adminMiddlewares) {
            foreach ($adminMiddlewares as $adminMiddleware) {
                $router->pushMiddlewareToGroup('admin', $adminMiddleware);
            }
        }
    }

    /**
     * Get plugin middlewares
     *
     * @param $path
     * @return array
     */
    private function loadMiddlewares($path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $middlewares = [];
        $files       = glob("$path/*");
        foreach ($files as $file) {
            $baseName      = basename($file, '.php');
            $namespacePath = 'Plugin'.dirname(str_replace($this->pluginBasePath, '', $file)).'/';
            $className     = str_replace('/', '\\', $namespacePath.$baseName);

            if (class_exists($className)) {
                $middlewares[] = $className;
            }
        }

        return $middlewares;
    }

    /**
     * Load design page builder components from plugins
     *
     * @throws \Exception
     */
    protected function loadDesignComponents($pluginCode): void
    {
        $pluginBasePath = $this->pluginBasePath;
        $builderPath    = "{$pluginBasePath}/{$pluginCode}/Admin/View/DesignBuilders/";

        $builders = glob($builderPath.'*');
        foreach ($builders as $builder) {
            $builderName   = basename($builder, '.php');
            $aliasName     = Str::snake($builderName);
            $componentName = Str::studly($builderName);
            $classBaseName = "\\Plugin\\{$pluginCode}\\Admin\\View\\DesignBuilders\\{$componentName}";

            if (! class_exists($classBaseName)) {
                throw new \Exception("请先定义自定义模板类 {$classBaseName}");
            }

            $this->loadViewComponentsAs('editor', [
                $aliasName => $classBaseName,
            ]);
        }
    }
}
