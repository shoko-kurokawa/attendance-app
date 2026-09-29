<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * その日の全一般ユーザーの勤怠情報が正確に表示される
     */
    public function test_all_users_attendance_is_displayed_correctly(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user1 = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => 'テスト花子',
            'admin_status' => false,
        ]);

        $attendance1 = Attendance::create([
            'user_id' => $user1->id,
            'date' => '2026-09-29',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance1->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $attendance2 = Attendance::create([
            'user_id' => $user2->id,
            'date' => '2026-09-29',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $attendance2->breaks()->create([
            'break_start' => '14:00:00',
            'break_end' => '14:30:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=2026-09-29');
        $response->assertStatus(200);

        // ユーザー1
        $response->assertSee('テスト太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');

        // ユーザー2
        $response->assertSee('テスト花子');
        $response->assertSee('10:00');
        $response->assertSee('19:00');

        // 休憩時間
        $response->assertSee('1:00');
        $response->assertSee('0:30');

        // 実働時間
        $response->assertSee('8:00');
        $response->assertSee('8:30');
    }

    /**
     * 勤怠一覧画面に遷移した際、現在の日付が表示される
     */
    public function test_current_date_is_displayed(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 9, 29, 10, 0, 0)
        );

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list');
        $response->assertStatus(200);
        $response->assertSee('2026年09月29日');
        $response->assertSee('2026/09/29');
    }

    /**
     * 「前日」を押下した時に前日の勤怠情報が表示される
     */
    public function test_previous_day_attendance_is_displayed(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '前日ユーザー',
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-28',
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=2026-09-28');
        $response->assertStatus(200);
        $response->assertSee('2026年09月28日');
        $response->assertSee('前日ユーザー');
        $response->assertSee('08:30');
        $response->assertSee('17:30');
    }

    /**
     * 「翌日」を押下した時に翌日の勤怠情報が表示される
     */
    public function test_next_day_attendance_is_displayed(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '翌日ユーザー',
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-30',
            'clock_in' => '10:15:00',
            'clock_out' => '19:15:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=2026-09-30');
        $response->assertStatus(200);
        $response->assertSee('2026年09月30日');
        $response->assertSee('翌日ユーザー');
        $response->assertSee('10:15');
        $response->assertSee('19:15');
    }
}