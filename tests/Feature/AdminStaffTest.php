<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'admin_status' => true,
        ]);
    }

    /**
     * 全一般ユーザーの氏名・メールアドレスが表示される
     */
    public function test_all_general_users_are_displayed(): void
    {
        $admin = $this->createAdmin();

        User::factory()->create([
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'admin_status' => false,
        ]);

        User::factory()->create([
            'name' => 'テスト花子',
            'email' => 'hanako@example.com',
            'admin_status' => false,
        ]);

        $response = $this->actingAs($admin)->get('/admin/staff/list');
        $response->assertStatus(200);
        $response->assertSee('テスト太郎');
        $response->assertSee('taro@example.com');
        $response->assertSee('テスト花子');
        $response->assertSee('hanako@example.com');
    }

    /**
     * 選択したユーザーの勤怠情報が正しく表示される
     */
    public function test_selected_users_attendance_is_displayed(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id . '?date=2026-09');
        $response->assertStatus(200);
        $response->assertSee('テスト太郎');
        $response->assertSee('09/15');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee('8:00');
    }

    /**
     * 「前月」を押下した時に前月の情報が表示される
     */
    public function test_previous_month_attendance_is_displayed(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '前月ユーザー',
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-08-15',
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id . '?date=2026-08');
        $response->assertStatus(200);
        $response->assertSee('2026/08');
        $response->assertSee('08/15');
        $response->assertSee('08:30');
        $response->assertSee('17:30');
    }

    /**
     * 「翌月」を押下した時に翌月の情報が表示される
     */
    public function test_next_month_attendance_is_displayed(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '翌月ユーザー',
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-15',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id . '?date=2026-10');
        $response->assertStatus(200);
        $response->assertSee('2026/10');
        $response->assertSee('10/15');
        $response->assertSee('10:00');
        $response->assertSee('19:00');
    }

    /**
     * 「詳細」を押下するとその日の管理者用勤怠詳細画面に遷移する
     */
    public function test_detail_link_goes_to_admin_attendance_detail(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id . '?date=2026-09');
        $response->assertStatus(200);

        // 月次一覧の「詳細」のリンク先を確認
        $response->assertSee(
            '/admin/attendance/' . $attendance->id,
            false
        );

        // 実際に詳細画面へアクセスできることも確認
        $response = $this->actingAs($admin)->get('/admin/attendance/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee('勤怠詳細');
    }
}