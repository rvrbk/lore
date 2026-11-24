<?php

namespace Tests\Feature;

use App\Models\JobScoutSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobScoutTrendTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_snapshot_command_stores_one_number_per_day(): void
    {
        config(['services.job_scout' => ['url' => 'https://scout.test', 'token' => 'secret']]);
        $user = User::factory()->create(['job_scout_email' => 'me@example.com']);
        User::factory()->create();
        Cache::flush();
        Http::fake(['scout.test/*' => Http::response(['new' => 7, 'url' => 'https://scout.test/x'])]);

        $this->artisan('jobscout:snapshot')->assertSuccessful();
        Cache::flush();
        Http::swap(new Factory);
        Http::fake(['scout.test/*' => Http::response(['new' => 9, 'url' => 'https://scout.test/x'])]);
        $this->artisan('jobscout:snapshot')->assertSuccessful();

        $this->assertSame(1, JobScoutSnapshot::count());
        $this->assertSame(9, JobScoutSnapshot::firstOrFail()->new_matches);
        $this->assertSame($user->id, JobScoutSnapshot::firstOrFail()->user_id);
    }

    public function test_the_trend_carries_the_last_known_number_over_days_without_a_snapshot(): void
    {
        $user = User::factory()->create();
        JobScoutSnapshot::create(['user_id' => $user->id, 'day' => today()->subDays(3), 'new_matches' => 4]);
        JobScoutSnapshot::create(['user_id' => $user->id, 'day' => today(), 'new_matches' => 10]);

        $this->assertSame([0, 0, 0, 4, 4, 4, 10], array_slice(JobScoutSnapshot::trend(7), 0, 7));
    }

    public function test_the_dashboard_still_renders_with_a_trend(): void
    {
        $this->actingAs($user = User::factory()->create());
        JobScoutSnapshot::create(['user_id' => $user->id, 'day' => today()->subDay(), 'new_matches' => 4]);

        $this->get(route('dashboard'))->assertOk();
    }
}
