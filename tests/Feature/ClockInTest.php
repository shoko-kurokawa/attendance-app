<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockInTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * 出勤ボタンが正しく機能する
     */
    public function test_user_can_clock_in(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 9, 0, 0));

        $user = User::factory()->create();

        // 勤務外では「出勤」ボタンが表示される
        $response = $this->actingAs($user)
            ->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤');

        // 出勤処理
        $response = $this->actingAs($user)->post('/attendance', ['action' => 'clock_in',]);
        $response->assertRedirect('/attendance');

        // DBに出勤時刻が保存される
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-09-29',
            'clock_in' => '09:00:00',
        ]);

        // 処理後は出勤中になる
        $response = $this->actingAs($user)->get('/attendance');
        $response->assertSee('出勤中');
    }

    /**
     * 出勤は1日1回のみできる
     */
    public function test_user_cannot_clock_in_twice_in_one_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 18, 0, 0));

        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => '17:00:00',
        ]);

        // 退勤済みの画面を開く
        $response = $this->actingAs($user)
            ->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('退勤済');

        // 出勤ボタンが表示されていない
        $response->assertDontSee('value="clock_in"', false);

        // Attendanceも1件だけ
        $this->assertDatabaseCount('attendances', 1);
    }

    /**
     * 出勤時刻が勤怠一覧画面で確認できる
     */
    public function test_clock_in_time_is_displayed_on_attendance_list(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 9, 15, 0));

        $user = User::factory()->create();

        // 出勤処理
        $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'clock_in',
            ]);

        // 勤怠一覧を表示
        $response = $this->actingAs($user)->get('/attendance/list?date=2026-09');
        $response->assertStatus(200);
        $response->assertSee('09:15');
    }
}