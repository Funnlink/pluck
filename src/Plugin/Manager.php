<?php
/**
 * Copyright (c) Since 2023 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Funnlink\Pluck\Plugin;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Manager
{
    private Collection $plugins;

    protected Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * Get all plugins from /plugins directory
     *
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function getPlugins(): Collection
    {
        if (! empty($this->plugins)) {
            return $this->plugins;
        }

        $existed = $this->getPluginsConfig();
        $plugins = new Collection();
        foreach ($existed as $dirname => $package) {
            $pluginPath = $this->getBaseDir().DIRECTORY_SEPARATOR.$dirname;
            $plugin     = new Plugin($pluginPath, $package);
            $plugin->setType(Arr::get($package, 'type'));
            $plugin->setDirname($dirname);
            $plugin->setName(Arr::get($package, 'name'));
            $plugin->setDescription(Arr::get($package, 'description'));
            $plugin->setVersion(Arr::get($package, 'version'));
            $plugin->setColumns();

            if ($plugins->has($plugin->code)) {
                continue;
            }

            $plugins->put($plugin->code, $plugin);
        }

        $this->plugins = $plugins->sortBy(function ($plugin) {
            return $plugin->code;
        });

        return $this->plugins;
    }

    /**
     * Get base plugin directory
     *
     * @return mixed
     */
    protected function getBaseDir(): mixed
    {
        return config('plugins.directory') ?: base_path('plugins');
    }

    /**
     * Get all plugin config
     *
     * @return array
     * @throws FileNotFoundException
     */
    protected function getPluginsConfig(): array
    {
        $installed = [];
        $resource = opendir($this->getBaseDir());
        while ($filename = @readdir($resource)) {
            if ($filename == '.' || $filename == '..') {
                continue;
            }
            $path = $this->getBaseDir() . DIRECTORY_SEPARATOR . $filename;
            if (is_dir($path)) {
                $packageJsonPath = $path . DIRECTORY_SEPARATOR . 'config.json';
                if (file_exists($packageJsonPath)) {
                    $installed[$filename] = json_decode($this->filesystem->get($packageJsonPath), true);
                }
            }
        }
        closedir($resource);

        return $installed;
    }
}
