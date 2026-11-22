<?php

namespace App\Console\Commands;

use App\Models\JobScoutSnapshot;
use App\Services\JobScout;
use Illuminate\Console\Command;

class SnapshotJobScout extends Command
{
    protected $signature = 'jobscout:snapshot';

    protected $description = 'Store today\'s number of new Job Scout matches for the dashboard trend';

    public function handle(JobScout $jobScout): int
    {
        $matches = $jobScout->newMatches();
        if ($matches === null) {
            $this->warn('Job Scout is not configured or did not answer.');

            return self::SUCCESS;
        }

        JobScoutSnapshot::updateOrCreate(['day' => today()->toDateString()], ['new_matches' => $matches['new']]);
        $this->info("Stored {$matches['new']} new matches.");

        return self::SUCCESS;
    }
}
