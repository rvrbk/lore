<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Services\GitHubHeatmapSnippet;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('lore:install-heatmap {site? : Name of one site; every site with a server folder when omitted}')]
#[Description('Add the click tracker script tag to the layout of each site\'s GitHub repository')]
class InstallHeatmap extends Command
{
    public function handle(GitHubHeatmapSnippet $snippet): int
    {
        $monitors = Monitor::whereNotNull('deploy_path')->when($this->argument('site'), fn ($query, string $site) => $query->where('name', $site))->orderBy('name')->get();
        if ($monitors->isEmpty()) {
            $this->error('No matching site with a server folder.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($monitors as $monitor) {
            try {
                $files = $snippet->install($monitor);
                $this->info("{$monitor->name}: ".($files === [] ? 'already in place.' : 'tracker added to '.implode(', ', $files).'.'));
            } catch (Throwable $e) {
                $failed = true;
                $this->error("{$monitor->name}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
