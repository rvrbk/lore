<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaultItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Concerns\UnlocksVault;
use Tests\TestCase;

class VaultSecurityTest extends TestCase
{
    use RefreshDatabase, UnlocksVault;

    private const SECRET = 'S3cret-Plaintext-Marker-7731';

    private User $user;

    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]); // production setting: the debug page prints request data
        $this->user = $this->userWithTwoFactor();

        $this->logFile = tempnam(sys_get_temp_dir(), 'lore-log-');
        config(['logging.default' => 'single', 'logging.channels.single.path' => $this->logFile, 'logging.channels.vault.driver' => 'single', 'logging.channels.vault.path' => $this->logFile]);
        Log::forgetChannel('single');
        Log::forgetChannel('vault');
    }

    protected function tearDown(): void
    {
        File::delete($this->logFile);
        parent::tearDown();
    }

    public function test_an_exception_while_saving_or_changing_an_item_does_not_leak_the_password_into_the_log_or_the_page(): void
    {
        VaultItem::saving(fn () => throw new RuntimeException('database exploded'));
        $item = VaultItem::withoutEvents(fn () => VaultItem::factory()->create(['user_id' => $this->user->id, 'password' => self::SECRET, 'username' => 'marker-user', 'notes' => 'marker-notes']));

        $this->unlockedAs($this->user);
        $created = $this->post(route('vault.store'), ['name' => 'New', 'vault_username' => 'marker-user', 'password' => self::SECRET, 'vault_notes' => 'marker-notes']);
        $updated = $this->put(route('vault.update', $item), ['name' => 'Changed', 'vault_username' => 'marker-user', 'password' => self::SECRET, 'vault_notes' => 'marker-notes']);
        $created->assertServerError();
        $updated->assertServerError();

        $log = File::get($this->logFile);
        $this->assertStringContainsString('database exploded', $log, 'the exception should have been logged');
        foreach ([$log, $created->getContent(), $updated->getContent()] as $output) {
            $this->assertStringNotContainsString(self::SECRET, $output);
            $this->assertStringNotContainsString('marker-user', $output);
            $this->assertStringNotContainsString('marker-notes', $output);
        }
    }

    public function test_a_failed_form_does_not_put_the_secrets_in_the_session(): void
    {
        $this->unlockedAs($this->user)->post(route('vault.store'), ['name' => '', 'vault_username' => 'marker-user', 'password' => self::SECRET, 'vault_notes' => 'marker-notes'])
            ->assertSessionHasErrors('name');

        $session = json_encode(session()->all());
        $this->assertStringNotContainsString(self::SECRET, $session);
        $this->assertStringNotContainsString('marker-user', $session);
        $this->assertStringNotContainsString('marker-notes', $session);
    }

    public function test_using_the_vault_never_writes_plaintext_to_logs_cache_queue_or_the_session(): void
    {
        Queue::fake();
        $item = VaultItem::factory()->create(['user_id' => $this->user->id, 'password' => self::SECRET]);

        $this->unlockedAs($this->user);
        $this->post(route('vault.store'), ['name' => 'Another', 'password' => self::SECRET]);
        $this->postJson(route('vault.reveal', $item))->assertOk();
        $this->get(route('vault.export'))->assertOk();

        Queue::assertNothingPushed();
        $this->assertStringNotContainsString(self::SECRET, File::get($this->logFile));
        $this->assertStringNotContainsString(self::SECRET, json_encode(session()->all()));
        $this->assertStringNotContainsString(self::SECRET, serialize(Cache::getStore()));
        $this->assertSame(0, DB::table('vault_items')->where('password', 'like', '%'.self::SECRET.'%')->count());
    }

    public function test_vault_responses_must_not_be_stored_by_browsers_or_proxies(): void
    {
        $this->unlockedAs($this->user);

        foreach ([$this->get(route('vault.index')), $this->get(route('vault.export'))] as $response) {
            $cacheControl = $response->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cacheControl);
            $this->assertStringContainsString('no-cache', $cacheControl);
            $this->assertStringContainsString('must-revalidate', $cacheControl);
        }
    }

    public function test_the_exception_trace_arguments_are_switched_off(): void
    {
        $this->assertSame('1', ini_get('zend.exception_ignore_args'));
    }
}
