<?php

namespace App\Console\Commands;

use App\Services\ServerHealth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('server:measure')]
#[Description('Record disk, memory, load and queue depth, and flag a disk that is filling up')]
class MeasureServer extends Command
{
    public function handle(ServerHealth $health): int
    {
        $metric = $health->measure();
        $this->info("Disk {$metric->disk_used_percent}% · memory {$metric->memory_used_percent}% · queue {$metric->queue_depth}.");

        return self::SUCCESS;
    }
}
