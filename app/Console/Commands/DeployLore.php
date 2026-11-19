<?php

namespace App\Console\Commands;

use App\Models\Deployment;
use App\Services\Deployer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('lore:deploy')]
#[Description('Run requested deployments (pull the checked-out branch and rebuild) and keep each site\'s "behind origin" status fresh')]
class DeployLore extends Command
{
    public function handle(Deployer $deployer): int
    {
        if (! $deployer->enabled()) {
            return self::SUCCESS;
        }

        Deployment::where('status', Deployment::RUNNING)->where('started_at', '<', now()->subHour())
            ->update(['status' => Deployment::FAILED, 'finished_at' => now(), 'output' => 'Timed out.']);

        $deployments = Deployment::where('status', Deployment::REQUESTED)->oldest('id')->get();
        foreach ($deployments as $deployment) {
            $deployer->perform($deployment);
            $this->info("Deployment #{$deployment->id} ({$deployment->site}) {$deployment->fresh()->status}.");
        }

        if ($deployments->isEmpty() && Cache::add('deploy.status-refresh', true, now()->addMinutes(5))) {
            foreach ($deployer->targets() as $target) {
                $deployer->refreshStatus($target['path']);
            }
        }

        return self::SUCCESS;
    }
}
