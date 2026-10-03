<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 勤怠詳細画面の「名前」がログインユーザーの氏名になっている
     */
    public function test_user_name_is_displayed_on_attendance_detail(): void
    {
        $user = User::factory()->create([
            'name' => 'テスト太郎',
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee('テスト太郎');
    }

    /**
     * 勤怠詳細画面の「日付」が選択した日付になっている
     */
    public function test_selected_date_is_displayed_on_attendance_detail(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee('2026年');
        $response->assertSee('9月15日');
    }

    /**
     * 出勤・退勤時間がログインユーザーの打刻と一致している
     */
    public function test_clock_in_and_clock_out_are_displayed_correctly(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '08:45:00',
            'clock_out' => '17:30:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee('08:45');
        $response->assertSee('17:30');
    }

    /**
     * 休憩時間がログインユーザーの打刻と一致している
     */
    public function test_break_times_are_displayed_correctly(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 1回目の休憩
        $attendance->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '12:45:00',
        ]);

        // 2回目の休憩
        $attendance->breaks()->create([
            'break_start' => '15:00:00',
            'break_end' => '15:15:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee('12:00');
        $response->assertSee('12:45');
        $response->assertSee('15:00');
        $response->assertSee('15:15');
    }
}