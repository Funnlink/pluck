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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Plugin
{
    public const TYPES = [
        'payment',
        'shipping',
        'theme',
        'feature',
        'total',
        'social',
        'language',
    ];

    public string $code;

    protected string $type;

    protected string $path;

    protected mixed $name;

    protected string $description;

    protected array $packageInfo;

    protected string $dirName;

    protected string $version;

    protected array $columns;

    public function __construct(string $path, array $packageInfo)
    {
        $this->path = $path;
        $this->packageInfo = $packageInfo;
    }

    public function __get($name)
    {
        return $this->packageInfoAttribute(Str::snake($name, '-'));
    }

    public function __isset($name)
    {
        return isset($this->{$name}) || $this->packageInfoAttribute(Str::snake($name, '-'));
    }

    public function packageInfoAttribute($name)
    {
        return Arr::get($this->packageInfo, $name);
    }

    /**
     * Set plugin Type
     *
     * @throws Exception
     */
    public function setType(string $type): self
    {
        if (!in_array($type, self::TYPES)) {
            throw new Exception('Invalid plugin type, must be one of ' . implode(',', self::TYPES));
        }
        $this->type = $type;

        return $this;
    }

    public function setDirname(string $dirName): self
    {
        $this->dirName = $dirName;

        return $this;
    }

    public function setName(string|array $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setDescription(string|array $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function setColumns(): self
    {
        $columnsPath = $this->path . DIRECTORY_SEPARATOR . 'columns.php';
        if (!file_exists($columnsPath)) {
            return $this;
        }
        $this->columns = require_once $columnsPath;

        return $this;
    }

    public function handleLabel()
    {
        $this->columns = collect($this->columns)->map(function ($item) {
            $item = $this->transLabel($item);
            if (isset($item['options'])) {
                $item['options'] = collect($item['options'])->map(function ($option) {
                    return $this->transLabel($option);
                })->toArray();
            }

            return $item;
        })->toArray();
    }

    private function transLabel($item): mixed
    {
        $labelKey = $item['label_key'] ?? '';
        $label = $item['label'] ?? '';
        if (empty($label) && $labelKey) {
            $languageKey = "$this->dirName::$labelKey";
            $item['label'] = trans($languageKey);
        }

        $descriptionKey = $item['description_key'] ?? '';
        $description = $item['description'] ?? '';
        if (empty($description) && $descriptionKey) {
            $languageKey = "$this->dirName::$descriptionKey";
            $item['description'] = trans($languageKey);
        }

        return $item;
    }

    public function getName(): mixed
    {
        return $this->name;
    }

    public function getLocaleName(): string
    {
        $currentLocale = admin_locale();

        if (is_array($this->name)) {
            if ($this->name[$currentLocale] ?? '') {
                return $this->name[$currentLocale];
            }

            return array_values($this->name)[0];
        }

        return (string)$this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLocaleDescription(): string
    {
        $currentLocale = admin_locale();

        if (is_array($this->description)) {
            if ($this->description[$currentLocale] ?? '') {
                return $this->description[$currentLocale];
            }

            return array_values($this->description)[0];
        }

        return $this->description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDirname(): string
    {
        return $this->dirName;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getEditUrl(): string
    {
        return panel_route('plugins.edit', ['code' => $this->code]);
    }

    public function getSetting($name = '')
    {
        if ($name) {
            return plugin_setting("$this->code.$name");
        }

        return plugin_setting($this->code);
    }

    public function getColumns(): array
    {
        $this->columns[] = SettingRepo::getPluginStatusColumn();
        $existValues = SettingRepo::getPluginColumns($this->code);
        foreach ($this->columns as $index => $column) {
            $dbColumn = $existValues[$column['name']] ?? null;
            $value = $dbColumn ? $dbColumn->value : null;
            if ($column['name'] == 'status') {
                $value = (int)$value;
            }
            $this->columns[$index]['value'] = $value;
        }

        return $this->columns;
    }

    public function validate($requestData): \Illuminate\Contracts\Validation\Validator
    {
        $rules = array_column($this->columns, 'rules', 'name');

        return Validator::make($requestData, $rules);
    }

    /**
     * 获取插件自定义编辑模板
     * @return string
     */
    public function getColumnView(): string
    {
        $viewFile = $this->getPath() . '/Views/admin/config.blade.php';
        if (file_exists($viewFile)) {
            return "$this->dirName::admin.config";
        }

        return '';
    }

    public function getBootFile(): string
    {
        return $this->getPath() . '/Init.php';
    }

    public function toArray(): array
    {
        return (array)array_merge([
            'name' => $this->name,
            'version' => $this->getVersion(),
            'path' => $this->path,
        ], $this->packageInfo);
    }
}
