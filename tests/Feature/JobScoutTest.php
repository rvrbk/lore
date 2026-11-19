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
        config(['services.job_scout' => ['url' => 'https://scout.test', 'token' => 'secret', 'email' => 'me@example.com']]);
        Cache::flush();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_dashboard_shows_the_new_vacancy_count_from_job_scout(): void
    {
        $this->configure();
        Http::fake(['scout.test/*' => Http::response(['new' => 7, 'url' => 'https://scout.test/jobs'])]);

        $this->get(route('dashboard'))->assertOk()->assertSee('New vacancies in Job Scout')->assertSee('7');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret') && $request['email'] === 'me@example.com');
    }

    public function test_nothing_is_shown_when_unconfigured_or_job_scout_is_down(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Vacancies');
        Http::assertNothingSent();

        $this->configure();
        Http::fake(['scout.test/*' => Http::response('boom', 500)]);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('New vacancies');
    }
}
