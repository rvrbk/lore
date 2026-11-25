<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireTwoFactor;
use App\Models\User;
use App\Models\VaultItem;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\UnlocksVault;
use Tests\TestCase;

class VaultTest extends TestCase
{
    use RefreshDatabase, UnlocksVault;

    private const SECRET = 'correct-horse-battery-staple-9X!';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->userWithTwoFactor();
    }

    public function test_secrets_are_encrypted_at_rest_and_readable_through_the_model(): void
    {
        $this->unlockedAs($this->user)->post(route('vault.store'), ['name' => 'Bank', 'url' => 'https://bank.example', 'vault_username' => 'rik-login', 'password' => self::SECRET, 'vault_notes' => 'pin 1234'])
            ->assertRedirect(route('vault.index'));

        $raw = DB::table('vault_items')->first();
        $this->assertSame('Bank', $raw->name);
        foreach ([$raw->username, $raw->password, $raw->notes] as $stored) {
            $this->assertStringNotContainsString('rik-login', $stored);
            $this->assertStringNotContainsString(self::SECRET, $stored);
            $this->assertStringNotContainsString('1234', $stored);
        }
        $this->assertNotSame(self::SECRET, $raw->password);

        $item = VaultItem::withoutGlobalScopes()->sole();
        $this->assertSame(self::SECRET, $item->password);
        $this->assertSame('rik-login', $item->username);
        $this->assertArrayNotHasKey('password', $item->toArray());
        $this->assertSame($this->user->id, $item->user_id);
    }

    public function test_the_list_and_edit_pages_never_contain_the_password(): void
    {
        $item = VaultItem::factory()->create(['user_id' => $this->user->id, 'name' => 'Mail', 'username' => 'me@example.com', 'password' => self::SECRET]);

        $this->unlockedAs($this->user)->get(route('vault.index'))->assertOk()->assertSee('Mail')->assertDontSee(self::SECRET)
            ->assertHeader('Cache-Control');
        $this->get(route('vault.edit', $item))->assertOk()->assertSee('me@example.com')->assertDontSee(self::SECRET);
    }

    public function test_search_matches_name_or_url_only(): void
    {
        VaultItem::factory()->create(['user_id' => $this->user->id, 'name' => 'GitHub', 'url' => 'https://github.com', 'username' => 'zebra']);
        VaultItem::factory()->create(['user_id' => $this->user->id, 'name' => 'Bank', 'url' => 'https://ing.nl']);

        $this->unlockedAs($this->user)->get(route('vault.index', ['q' => 'git']))->assertSee('GitHub')->assertDontSee('Bank');
        $this->get(route('vault.index', ['q' => 'ING.nl']))->assertSee('Bank')->assertDontSee('GitHub');
        $this->get(route('vault.index', ['q' => 'zebra']))->assertDontSee('GitHub');
    }

    public function test_editing_keeps_the_password_when_left_empty_and_changes_it_otherwise(): void
    {
        $item = VaultItem::factory()->create(['user_id' => $this->user->id, 'password' => self::SECRET]);

        $this->unlockedAs($this->user)->put(route('vault.update', $item), ['name' => 'Renamed', 'password' => ''])->assertRedirect(route('vault.index'));
        $this->assertSame(self::SECRET, $item->fresh()->password);
        $this->assertSame('Renamed', $item->fresh()->name);

        $this->put(route('vault.update', $item), ['name' => 'Renamed', 'password' => 'brand-new'])->assertRedirect();
        $this->assertSame('brand-new', $item->fresh()->password);

        $this->delete(route('vault.destroy', $item))->assertRedirect(route('vault.index'));
        $this->assertDatabaseCount('vault_items', 0);
    }

    public function test_a_password_is_required_for_new_items(): void
    {
        $this->unlockedAs($this->user)->post(route('vault.store'), ['name' => 'No password'])->assertSessionHasErrors('password');
    }

    public function test_users_cannot_reach_each_others_items(): void
    {
        $theirs = VaultItem::factory()->create(['user_id' => User::factory()->create()->id, 'password' => self::SECRET]);

        $this->unlockedAs($this->user);
        $this->post(route('vault.reveal', $theirs))->assertNotFound();
        $this->get(route('vault.edit', $theirs))->assertNotFound();
        $this->put(route('vault.update', $theirs), ['name' => 'Mine now'])->assertNotFound();
        $this->delete(route('vault.destroy', $theirs))->assertNotFound();
        $this->get(route('vault.index'))->assertDontSee($theirs->name);

        $this->assertNull($theirs->fresh()->last_revealed_at);
        $this->assertSame($theirs->name, $theirs->fresh()->name);
    }

    public function test_the_policy_refuses_other_users_even_without_the_ownership_scope(): void
    {
        $theirs = VaultItem::factory()->create(['user_id' => User::factory()->create()->id]);
        $mine = VaultItem::factory()->create(['user_id' => $this->user->id]);

        foreach (['view', 'update', 'delete', 'reveal'] as $ability) {
            $this->assertFalse($this->user->can($ability, $theirs), $ability);
            $this->assertTrue($this->user->can($ability, $mine), $ability);
        }
    }

    public function test_reveal_returns_the_password_updates_the_timestamp_and_logs_without_the_value(): void
    {
        $logFile = $this->useLogFile('vault');
        $item = VaultItem::factory()->create(['user_id' => $this->user->id, 'password' => self::SECRET]);

        $response = $this->unlockedAs($this->user)->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson(route('vault.reveal', $item));

        $response->assertOk()->assertExactJson(['password' => self::SECRET]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertNotNull($item->fresh()->last_revealed_at);

        $log = File::get($logFile);
        $this->assertStringContainsString('vault item revealed', $log);
        $this->assertStringContainsString('"user_id":'.$this->user->id, $log);
        $this->assertStringContainsString('"item_id":'.$item->id, $log);
        $this->assertStringContainsString('203.0.113.9', $log);
        $this->assertStringNotContainsString(self::SECRET, $log);
    }

    public function test_the_vault_needs_two_factor_in_this_session(): void
    {
        $item = VaultItem::factory()->create(['user_id' => $this->user->id]);

        $plain = User::factory()->create();
        $this->actingAs($plain)->get(route('vault.index'))->assertRedirect(route('settings.two-factor'));

        // Signed in by a remember-me cookie: two-factor is on but this session has not passed the challenge.
        $this->actingAs($this->user)->withSession(['auth.password_confirmed_at' => time()]);
        $this->get(route('vault.index'))->assertRedirect(route('two-factor.challenge'));
        $this->postJson(route('vault.reveal', $item))->assertStatus(423);
    }

    public function test_guests_cannot_use_the_vault(): void
    {
        $this->get(route('vault.index'))->assertRedirect(route('login'));
        $this->get(route('vault.export'))->assertRedirect(route('login'));
        $this->post(route('vault.export.download'))->assertRedirect(route('login'));
    }

    public function test_the_vault_asks_for_the_password_again_after_idle_time(): void
    {
        config(['vault.idle_lock_minutes' => 10]);
        $item = VaultItem::factory()->create(['user_id' => $this->user->id]);

        $this->unlockedAs($this->user)->withSession(['vault.last_activity' => time() - 120])->get(route('vault.index'))->assertOk();

        $this->withSession(['vault.last_activity' => time() - 11 * 60])->get(route('vault.index'))->assertRedirect(route('password.confirm'));
        $this->unlockedAs($this->user)->withSession(['vault.last_activity' => time() - 11 * 60])->postJson(route('vault.reveal', $item))->assertStatus(423);
        $this->assertNull($item->fresh()->last_revealed_at);
    }

    public function test_every_vault_route_has_csrf_protection_two_factor_a_password_confirmation_and_a_rate_limit(): void
    {
        $this->app->make(Kernel::class); // syncs the middleware groups into the router
        $router = $this->app['router'];
        $routes = collect($router->getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with((string) $route->getName(), 'vault.'));
        $this->assertCount(9, $routes);

        foreach ($routes as $route) {
            $stack = collect($router->resolveMiddleware($route->gatherMiddleware()));
            $this->assertTrue($stack->contains(PreventRequestForgery::class) || $stack->contains(ValidateCsrfToken::class), $route->getName().' lacks CSRF protection');
            $this->assertTrue($stack->contains(fn ($m) => is_string($m) && str_starts_with($m, Authenticate::class)), $route->getName().' lacks auth');
            $this->assertTrue($stack->contains(RequireTwoFactor::class), $route->getName().' lacks two-factor');
            $this->assertTrue($stack->contains(fn ($m) => is_string($m) && str_starts_with($m, RequirePassword::class)), $route->getName().' lacks password.confirm');
            $this->assertTrue($stack->contains(fn ($m) => is_string($m) && str_starts_with($m, ThrottleRequests::class.':')), $route->getName().' lacks a rate limit');
        }
    }

    /** Points a log channel (and the default one) at a temporary file and returns its path. */
    private function useLogFile(string $channel): string
    {
        $file = tempnam(sys_get_temp_dir(), 'lore-log-');
        config(['logging.default' => 'single', 'logging.channels.single.path' => $file, "logging.channels.$channel.driver" => 'single', "logging.channels.$channel.path" => $file]);
        Log::forgetChannel('single');
        Log::forgetChannel($channel);
        $this->beforeApplicationDestroyed(fn () => File::delete($file));

        return $file;
    }
}
