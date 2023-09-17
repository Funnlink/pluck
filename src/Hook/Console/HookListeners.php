<?php
/**
 * Copyright (c) Since 2023 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Funnlink\Pluck\Hook\Console;

use Illuminate\Console\Command;
use Funnlink\Pluck\Hook\Facades\Hook;

class HookListeners extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hook:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all hook listeners';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $list = Hook::getListeners();
        $array = [];

        foreach ($list as $hook => $lister) {
            foreach ($lister as $key => $element) {
                $array[] = [
                    $key,
                    $hook,
                    $element['caller']['class'],
                ];
            }
        }

        $headers = ['Sort', 'Hook name', 'Listener class'];

        $this->table($headers, $array);
    }
}
