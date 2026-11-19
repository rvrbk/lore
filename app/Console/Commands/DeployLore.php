<?php

namespace App\Console\Commands;

use App\Models\Deployment;
use App\Services\Deployer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lore:deploy')]
#[Description('Run a requested deployment (pull the checked-out branch and rebuild) and keep the "behind origin" status fresh')]
class DeployLore extends Command
{
    public function handle(Deployer $deployer): int
    {
        if (! $deployer->enabled()) {
            return self::SUCCESS;
        }

        Deployment::where('status', Deployment::RUNNING)->where('started_at', '<', now()->subHour())
            ->update(['status' => Deployment::FAILED, 'finished_at' => now(), 'output' => 'Timed out.']);

        $deployment = Deployment::where('status', Deployment::REQUESTED)->oldest()->first();
        if ($deployment) {
            $deployer->perform($deployment);
            $this->info("Deployment #{$deployment->id} {$deployment->fresh()->status}.");

            return self::SUCCESS;
        }

        $checkedAt = $deployer->status()['checked_at'] ?? null;
        if ($checkedAt === null || now()->diffInMinutes($checkedAt, true) >= 5) {
            $deployer->refreshStatus();
        }

        return self::SUCCESS;
    }
}
