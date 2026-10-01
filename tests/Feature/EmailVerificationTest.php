<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\VerifyEmail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 会員登録後に認証メールが送信される
     */
    public function test_verification_email_is_sent_after_registration(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'テストユーザー',
            'email' => 'verification@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'verification@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * 未認証ユーザーはメール認証誘導画面を表示できる
     */
    public function test_unverified_user_can_view_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/email/verify');
        $response->assertStatus(200);
        $response->assertSee('メール認証を完了してください');
        $response->assertSee('認証はこちらから');
        $response->assertSee('認証メールを再送する');
        $response->assertSee('http://localhost:8025', false);
    }

    /**
     * メール認証を完了すると勤怠登録画面へ遷移する
     */
    public function test_user_can_verify_email_and_is_redirected_to_attendance(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $response = $this->actingAs($user)->get($verificationUrl);
        $response->assertRedirect('/attendance?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);

        Event::assertDispatched(Verified::class);
    }

    /**
     * 認証メールを再送できる
     */
    public function test_verification_email_can_be_resent(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->post('/email/verification-notification');
        $response->assertRedirect();

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
