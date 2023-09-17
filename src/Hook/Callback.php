<?php
/**
 * Copyright (c) Since 2023 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Funnlink\Pluck\Hook;

class Callback
{
    protected $function;

    protected array $parameters = [];

    protected bool $run = true;

    public function __construct($function, $parameters = [])
    {
        $this->setCallback($function, $parameters);
    }

    public function setCallback($function, $parameters)
    {
        $this->function = $function;
        $this->parameters = $parameters;
    }

    public function call($parameters = null)
    {
        if ($this->run) {
            $this->run = false;

            return call_user_func_array($this->function, ($parameters ?: $this->parameters));
        }

        return '';
    }

    public function reset()
    {
        $this->run = true;
    }
}
