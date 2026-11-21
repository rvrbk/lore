<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Services\GitHubDeployHook;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('lore:install-deploy-hook {site? : Name of one site; every site with a server folder when omitted}')]
#[Description('Add the deploy-on-push workflow and secret to the GitHub repository of each site')]
class InstallDeployHook extends Command
{
    public function handle(GitHubDeployHook $hook): int
    {
        $monitors = Monitor::whereNotNull('deploy_path')->when($this->argument('site'), fn ($query, string $site) => $query->where('name', $site))->orderBy('name')->get();
        if ($monitors->isEmpty()) {
            $this->error('No matching site with a server folder.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($monitors as $monitor) {
            try {
                $this->info("{$monitor->name}: set up on ".$hook->install($monitor).'.');
            } catch (Throwable $e) {
                $failed = true;
                $this->error("{$monitor->name}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
