<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreakTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * 休憩ボタンが正しく機能する
     */
    public function test_user_can_start_break(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 12, 0, 0));

        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 出勤中は「休憩入」が表示される
        $response = $this->actingAs($user)
            ->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩入');

        // 休憩開始
        $response = $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'break_in',
            ]);

        $response->assertRedirect('/attendance');

        // 休憩開始時刻が保存される
        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $user->attendances()->first()->id,
            'break_start' => '12:00:00',
            'break_end' => null,
        ]);

        // ステータスが休憩中になる
        $response = $this->actingAs($user)
            ->get('/attendance');

        $response->assertSee('休憩中');
    }

    /**
     * 休憩は1日に何回でもできる
     */
    public function test_user_can_take_break_multiple_times(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 1回目の休憩
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 12, 0, 0));

        $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'break_in',
            ]);

        Carbon::setTestNow(Carbon::create(2026, 9, 29, 12, 30, 0));

        $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'break_out',
            ]);

        // 2回目の休憩
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 15, 0, 0));

        $response = $this->actingAs($user)
            ->get('/attendance');

        $response->assertSee('休憩入');

        $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'break_in',
            ]);

        // 休憩レコードが2件作られている
        $this->assertDatabaseCount('breaks', 2);

        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_start' => '15:00:00',
            'break_end' => null,
        ]);
    }

    /**
     * 休憩戻ボタンが正しく機能する
     */
    public function test_user_can_end_break(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 12, 0, 0));

        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 休憩開始
        $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'break_in',
            ]);

        // 休憩中は「休憩戻」が表示される
        $response = $this->actingAs($user)
            ->get('/attendance');

        $response->assertSee('休憩戻');

        // 12:30に休憩終了
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 12, 30, 0));

        $response = $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'break_out',
            ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_start' => '12:00:00',
            'break_end' => '12:30:00',
        ]);

        // 出勤中へ戻る
        $response = $this->actingAs($user)
            ->get('/attendance');

        $response->assertSee('出勤中');
    }

    /**
     * 休憩戻は1日に何回でもできる
     */
    public function test_user_can_end_break_multiple_times(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => today(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        // 1回目 12:00〜12:30
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 12, 0, 0));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 9, 29, 12, 30, 0));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);

        // 2回目 15:00〜
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 15, 0, 0));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        // 2回目でも休憩戻が表示される
        $response = $this->actingAs($user)
            ->get('/attendance');

        $response->assertSee('休憩戻');

        Carbon::setTestNow(Carbon::create(2026, 9, 29, 15, 15, 0));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);

        // 2回目も正常に終了している
        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_start' => '15:00:00',
            'break_end' => '15:15:00',
        ]);

        $this->assertDatabaseCount('breaks', 2);
    }

    /**
     * 休憩時刻が勤怠一覧画面で確認できる
     */
    public function test_break_time_is_displayed_on_attendance_list(): void
    {
        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-29',
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        Carbon::setTestNow(Carbon::create(2026, 9, 29, 12, 0, 0));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_in',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 9, 29, 13, 0, 0));

        $this->actingAs($user)->post('/attendance', [
            'action' => 'break_out',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list?date=2026-09');
        $response->assertStatus(200);

        // 12:00〜13:00なので休憩合計は1時間
        $response->assertSee('1:00');
    }
}