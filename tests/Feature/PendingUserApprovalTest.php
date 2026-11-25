<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PendingUserApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $name): void
    {
        $this->post(route('register'), ['username' => $name, 'email' => "$name@example.com", 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass']);
    }

    public function test_the_first_account_is_approved_and_signed_in_straight_away(): void
    {
        $this->register('rik');

        $this->assertAuthenticated();
        $this->assertTrue(User::firstOrFail()->isApproved());
    }

    public function test_later_accounts_wait_for_approval_and_cannot_log_in_and_admins_get_a_telegram_message(): void
    {
        config(['services.telegram' => ['token' => 'bot-token']]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'telegram_chat_id' => '111']);
        User::factory()->create(['telegram_chat_id' => '222']);

        $this->register('eve');

        $this->assertGuest();
        $this->assertFalse(User::where('username', 'eve')->firstOrFail()->isApproved());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['chat_id'] === '111' && str_contains($request['text'], 'eve') && str_contains($request['text'], route('admin.users.index')));

        $this->post(route('login'), ['username' => 'eve', 'password' => 'secret-pass'])->assertSessionHasErrors(['username' => 'Your account is waiting for approval.']);
        $this->assertGuest();
    }

    public function test_an_admin_can_approve_and_the_user_can_then_log_in(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $eve = User::factory()->pending()->create(['username' => 'eve', 'password' => 'secret-pass']);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('eve');
        $this->post(route('admin.users.approve', $eve))->assertRedirect();
        $this->post(route('logout'));

        $this->assertTrue($eve->fresh()->isApproved());
        $this->post(route('login'), ['username' => 'eve', 'password' => 'secret-pass'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($eve);
    }

    public function test_an_admin_can_reject_a_pending_account_but_not_remove_an_approved_one(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $eve = User::factory()->pending()->create();
        $sam = User::factory()->create();

        $this->actingAs($admin);
        $this->delete(route('admin.users.reject', $eve))->assertRedirect();
        $this->delete(route('admin.users.reject', $sam))->assertStatus(422);

        $this->assertModelMissing($eve);
        $this->assertModelExists($sam);
    }

    public function test_regular_users_cannot_see_or_use_the_approvals(): void
    {
        $eve = User::factory()->pending()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->post(route('admin.users.approve', $eve))->assertForbidden();
        $this->delete(route('admin.users.reject', $eve))->assertForbidden();
        $this->assertFalse($eve->fresh()->isApproved());
    }
}
