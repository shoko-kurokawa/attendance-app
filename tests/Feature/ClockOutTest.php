<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * 退勤ボタンが正しく機能する
     */
    public function test_user_can_clock_out(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 9, 29, 18, 0, 0)
        );

        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 出勤中は「退勤」ボタンが表示される
        $response = $this->actingAs($user)
            ->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('退勤');

        // 退勤処理
        $response = $this->actingAs($user)->post('/attendance', ['action' => 'clock_out',]);

        $response->assertRedirect('/attendance');

        // 退勤時刻がDBに保存されている
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 処理後は「退勤済」になる
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('退勤済');
    }

    /**
     * 退勤時刻が勤怠一覧画面で確認できる
     */
    public function test_clock_out_time_is_displayed_on_attendance_list(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 9, 29, 18, 30, 0)
        );

        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 18:30に退勤
        $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'clock_out',
            ]);

        // 勤怠一覧を表示
        $response = $this->actingAs($user)->get('/attendance/list?date=2026-09');
        $response->assertStatus(200);

        // 退勤時刻が正しく表示されている
        $response->assertSee('18:30');
    }
}