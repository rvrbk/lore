<?php

namespace App\Console\Commands;

use App\Models\JobScoutSnapshot;
use App\Models\User;
use App\Services\JobScout;
use Illuminate\Console\Command;

class SnapshotJobScout extends Command
{
    protected $signature = 'jobscout:snapshot';

    protected $description = 'Store today\'s number of new Job Scout matches for the dashboard trend';

    public function handle(JobScout $jobScout): int
    {
        foreach (User::whereNotNull('job_scout_email')->get() as $user) {
            $matches = $jobScout->newMatches($user);
            if ($matches === null) {
                $this->warn("Job Scout is not configured or did not answer for {$user->username}.");

                continue;
            }

            JobScoutSnapshot::updateOrCreate(['user_id' => $user->id, 'day' => today()->toDateString()], ['new_matches' => $matches['new']]);
            $this->info("Stored {$matches['new']} new matches for {$user->username}.");
        }

        return self::SUCCESS;
    }
}
