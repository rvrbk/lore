<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobScoutTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        config(['services.job_scout' => ['url' => 'https://scout.test', 'token' => 'secret']]);
        Cache::flush();
        $this->actingAs(User::factory()->create(['job_scout_email' => 'me@example.com']));
    }

    public function test_the_dashboard_shows_the_new_vacancy_count_from_job_scout(): void
    {
        $this->configure();
        Http::fake(['scout.test/*' => Http::response(['new' => 7, 'url' => 'https://scout.test/jobs'])]);

        $this->get(route('dashboard'))->assertOk()->assertSee('New vacancies in Job Scout')->assertSee('7');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret') && $request['email'] === 'me@example.com');
    }

    public function test_nothing_is_shown_to_a_user_without_a_job_scout_account_or_when_job_scout_is_down(): void
    {
        config(['services.job_scout' => ['url' => 'https://scout.test', 'token' => 'secret']]);
        $this->actingAs(User::factory()->create());
        Http::fake();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('New vacancies');
        Http::assertNothingSent();

        $this->configure();
        Http::fake(['scout.test/*' => Http::response('boom', 500)]);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('New vacancies');
    }
}
