<?php

namespace App\Console\Commands;

use App\Services\SiteUsers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sites:count-users')]
#[Description('Count the users table of every registered site for the dashboard')]
class CountSiteUsers extends Command
{
    public function handle(SiteUsers $siteUsers): int
    {
        foreach ($siteUsers->refresh() as $site) {
            $this->line($site['name'].': '.($site['users'] ?? 'unavailable'));
        }

        return self::SUCCESS;
    }
}
