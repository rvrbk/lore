<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaultItem;
use App\Services\Vault\BitwardenCsv;
use App\Services\Vault\VaultSealer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\UnlocksVault;
use Tests\TestCase;

class VaultExportTest extends TestCase
{
    use RefreshDatabase, UnlocksVault;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->userWithTwoFactor();
    }

    public function test_the_export_needs_a_recent_password_confirmation(): void
    {
        VaultItem::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->withSession(['two_factor.passed' => true])->get(route('vault.export'))->assertRedirect(route('password.confirm'));
        $this->unlockedAs($this->user)->get(route('vault.export'))->assertOk();
    }

    public function test_the_csv_uses_bitwardens_import_columns(): void
    {
        VaultItem::factory()->create(['user_id' => $this->user->id, 'name' => 'Bank, "main"', 'url' => 'https://bank.example', 'username' => 'rik', 'password' => 'p@ss,"word"', 'notes' => "line one\nline two"]);
        VaultItem::factory()->create(['user_id' => User::factory()->create()->id, 'name' => 'Not mine']);

        $response = $this->unlockedAs($this->user)->get(route('vault.export'));
        $response->assertOk()->assertDownload();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $rows = $this->parse($response->streamedContent());
        $this->assertSame(['folder', 'favorite', 'type', 'name', 'notes', 'fields', 'reprompt', 'login_uri', 'login_username', 'login_password', 'login_totp'], $rows[0]);
        $this->assertSame(BitwardenCsv::COLUMNS, $rows[0]);
        $this->assertCount(2, $rows, 'header plus only my own item');
        $this->assertSame(['', '', 'login', 'Bank, "main"', "line one\nline two", '', '0', 'https://bank.example', 'rik', 'p@ss,"word"', ''], $rows[1]);
    }

    public function test_the_response_may_not_be_cached(): void
    {
        $cacheControl = $this->unlockedAs($this->user)->get(route('vault.export'))->headers->get('Cache-Control');

        foreach (['no-store', 'no-cache', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }
    }

    public function test_exports_are_limited_to_three_an_hour_and_logged_without_content(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'lore-log-');
        config(['logging.channels.vault.driver' => 'single', 'logging.channels.vault.path' => $file]);
        Log::forgetChannel('vault');
        VaultItem::factory()->create(['user_id' => $this->user->id, 'password' => 'export-marker-4411']);

        $this->unlockedAs($this->user);
        foreach (range(1, 3) as $ignored) {
            $this->get(route('vault.export'))->assertOk()->streamedContent();
        }
        $this->get(route('vault.export'))->assertStatus(429);

        $log = File::get($file);
        File::delete($file);
        $this->assertSame(3, substr_count($log, 'vault exported'));
        $this->assertStringContainsString('"user_id":'.$this->user->id, $log);
        $this->assertStringNotContainsString('export-marker-4411', $log);
    }

    public function test_other_vault_requests_do_not_use_up_the_export_limit(): void
    {
        $item = VaultItem::factory()->create(['user_id' => $this->user->id]);

        $this->unlockedAs($this->user);
        foreach (range(1, 5) as $ignored) {
            $this->postJson(route('vault.reveal', $item))->assertOk();
        }
        $this->get(route('vault.export'))->assertOk();
    }

    public function test_the_encrypted_export_can_only_be_opened_with_the_private_key(): void
    {
        $this->skipWithoutSodium();
        $keys = $this->makeKeypair();
        config(['vault.public_key' => $keys['public']]);
        $this->app->forgetInstance(VaultSealer::class);
        VaultItem::factory()->create(['user_id' => $this->user->id, 'name' => 'Secret site', 'password' => 'sealed-marker-8842']);

        $response = $this->unlockedAs($this->user)->get(route('vault.export', ['format' => 'encrypted']));

        $response->assertOk();
        $sealed = $response->getContent();
        $this->assertStringStartsWith(VaultSealer::MAGIC, $sealed);
        $this->assertStringNotContainsString('sealed-marker-8842', $sealed);
        $this->assertStringNotContainsString('Secret site', $sealed);

        $csv = VaultSealer::open($sealed, $keys['secret']);
        $this->assertSame('sealed-marker-8842', $this->parse($csv)[1][9]);
        $this->expectException(\RuntimeException::class);
        VaultSealer::open($sealed, $this->makeKeypair()['secret']);
    }

    public function test_the_encrypted_export_is_refused_without_a_public_key(): void
    {
        config(['vault.public_key' => null]);
        $this->app->forgetInstance(VaultSealer::class);

        $this->unlockedAs($this->user)->get(route('vault.export', ['format' => 'encrypted']))->assertRedirect(route('vault.index'))->assertSessionHas('error');
        $this->get(route('vault.index'))->assertOk()->assertSee('Export encrypted (not set up)');
        $this->get(route('vault.export', ['format' => 'bogus']))->assertStatus(422);
    }

    /** @return list<list<string|null>> */
    private function parse(string $csv): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }
}
