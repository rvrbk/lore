<?php

namespace Tests\Feature;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class TelegramSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function bot(array $updates = []): void
    {
        config(['services.telegram' => ['token' => 'bot-token']]);
        Cache::flush();
        Http::fake([
            'api.telegram.org/botbot-token/getMe' => Http::response(['result' => ['username' => 'lore_bot']]),
            'api.telegram.org/botbot-token/getUpdates*' => Http::response(['result' => $updates]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
    }

    private function message(string $text, int $chatId, string $name = 'Sam'): array
    {
        return ['message' => ['text' => $text, 'chat' => ['id' => $chatId, 'first_name' => $name]]];
    }

    public function test_the_page_explains_how_to_connect_with_a_private_code_and_a_link_to_the_bot(): void
    {
        $this->bot();
        $this->actingAs($user = User::factory()->create());

        $code = $this->get(route('settings.telegram'))->assertOk()->assertSee('t.me/lore_bot?start=', false)->assertSee('Check connection');

        $this->assertNotNull($user->fresh()->telegram_link_code);
        $code->assertSee('/start '.$user->fresh()->telegram_link_code);
    }

    public function test_the_page_says_so_when_the_bot_is_not_set_up(): void
    {
        config(['services.telegram' => ['token' => null]]);
        $this->actingAs(User::factory()->create());

        $this->get(route('settings.telegram'))->assertOk()->assertSee('TELEGRAM_BOT_TOKEN')->assertDontSee('Check connection');
    }

    public function test_sending_the_bot_the_code_connects_that_chat_to_the_user(): void
    {
        $user = User::factory()->create(['telegram_link_code' => 'secretcode']);
        $this->bot([$this->message('hello', 1), $this->message('/start secretcode', 555), $this->message('/start someoneelses', 777)]);
        $this->actingAs($user);

        $this->post(route('settings.telegram.check'))->assertRedirect(route('settings.telegram'));

        $user->refresh();
        $this->assertSame('555', $user->telegram_chat_id);
        $this->assertNull($user->telegram_link_code);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage') && $request['chat_id'] === '555');
    }

    public function test_nothing_is_connected_until_the_bot_has_received_the_code(): void
    {
        $user = User::factory()->create(['telegram_link_code' => 'secretcode']);
        $this->bot([$this->message('/start wrongcode', 555)]);
        $this->actingAs($user);

        $this->post(route('settings.telegram.check'))->assertSessionHasErrors('telegram');
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_a_chat_cannot_be_connected_to_two_accounts(): void
    {
        User::factory()->create(['telegram_chat_id' => '555']);
        $user = User::factory()->create(['telegram_link_code' => 'secretcode']);
        $this->bot([$this->message('/start secretcode', 555)]);
        $this->actingAs($user);

        $this->post(route('settings.telegram.check'))->assertSessionHasErrors('telegram');
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_a_user_can_test_and_disconnect_their_chat(): void
    {
        $this->bot();
        $this->actingAs($user = User::factory()->create(['telegram_chat_id' => '555']));

        $this->post(route('settings.telegram.test'))->assertSessionHas('status');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage') && $request['chat_id'] === '555');

        $this->delete(route('settings.telegram.disconnect'))->assertRedirect(route('settings.telegram'));
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_reminders_only_go_to_their_owner_and_users_without_telegram_are_not_retried(): void
    {
        Process::fake();
        $this->bot();
        $withTelegram = User::factory()->create(['telegram_chat_id' => '111']);
        $withoutTelegram = User::factory()->create();
        User::factory()->create(['telegram_chat_id' => '222']);
        $mine = Reminder::factory()->create(['user_id' => $withTelegram->id, 'title' => 'Mine', 'due_at' => now()->subMinute()]);
        $theirs = Reminder::factory()->create(['user_id' => $withoutTelegram->id, 'title' => 'Theirs', 'due_at' => now()->subMinute()]);

        $this->artisan('reminders:notify')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['chat_id'] === '111' && str_contains($request['text'], 'Mine'));
        $this->assertNotNull($mine->fresh()->notified_at);
        $this->assertNotNull($theirs->fresh()->notified_at);
    }

    public function test_the_connect_page_needs_a_login(): void
    {
        $this->get(route('settings.telegram'))->assertRedirect(route('login'));
    }

    public function test_the_morning_digest_only_goes_to_connected_subscribers_and_users_can_switch_it_on_and_off(): void
    {
        $this->bot();
        $subscriber = User::factory()->create(['telegram_chat_id' => '111', 'digest_subscribed' => true]);
        User::factory()->create(['telegram_chat_id' => '222']);
        User::factory()->create(['telegram_chat_id' => null, 'digest_subscribed' => true]);

        $this->artisan('lore:digest')->assertSuccessful();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['chat_id'] === '111' && str_contains($request['text'], 'Good morning'));

        $this->actingAs($subscriber);
        $this->get(route('settings.telegram'))->assertSee('Send me the morning digest');
        $this->put(route('settings.telegram.digest'), ['digest_subscribed' => '0'])->assertRedirect(route('settings.telegram'));
        $this->assertFalse($subscriber->fresh()->digest_subscribed);
        $this->put(route('settings.telegram.digest'), ['digest_subscribed' => '1']);
        $this->assertTrue($subscriber->fresh()->digest_subscribed);
    }
}
