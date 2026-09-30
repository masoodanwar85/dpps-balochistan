<?php

namespace App\Console\Commands;

use App\Services\Statuses\StatusRefresh;
use Illuminate\Console\Command;

class RefreshStatuses extends Command
{
    protected $signature = 'statuses:refresh';

    protected $description = 'Update license, company and dealer statuses, and open renewal-window notices';

    public function handle(StatusRefresh $refresh): int
    {
        $refresh->run();
        $this->info('Statuses refreshed.');

        return self::SUCCESS;
    }
}
