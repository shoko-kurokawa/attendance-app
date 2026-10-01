<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * メールアドレスが未入力の場合、
     * バリデーションメッセージが表示される
     */
    public function test_email_is_required(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'admin_status' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => '',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);

        $this->assertGuest();
    }

    /**
     * パスワードが未入力の場合、
     * バリデーションメッセージが表示される
     */
    public function test_password_is_required(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'admin_status' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);

        $this->assertGuest();
    }

    /**
     * 登録内容と一致しない場合、
     * バリデーションメッセージが表示される
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'admin_status' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);

        $this->assertGuest();
    }

    /**
     * 管理者はログアウトできる
     */
    public function test_admin_can_logout(): void
    {
        $admin = User::factory()->create(['admin_status' => true,]);

        $this->actingAs($admin);
        $this->assertAuthenticatedAs($admin);

        $response = $this->post('/admin/logout');
        $response->assertRedirect('/admin/login');

        $this->assertGuest();
    }
}
