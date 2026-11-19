<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Services\ExpiryWatcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitors:check-expiry')]
#[Description('Look up SSL certificate and domain expiry dates, create countdown reminders and flag anything about to lapse')]
class CheckExpiry extends Command
{
    public function handle(ExpiryWatcher $watcher): int
    {
        $checked = $watcher->checkAll(Monitor::orderBy('id')->get());
        $this->info("Checked expiry for {$checked} site(s).");

        return self::SUCCESS;
    }
}
