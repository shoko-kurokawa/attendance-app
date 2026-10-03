<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テスト用の勤怠を作成
     */
    private function createAttendance(User $user): Attendance
    {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);

        $attendance->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        return $attendance;
    }

    /**
     * 出勤時間が退勤時間より後の場合エラーになる
     */
    public function test_clock_in_after_clock_out_is_invalid(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post('/attendance/detail/' . $attendance->id, [
                'new_clock_in' => '19:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '修正します',
            ]);

        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);
    }

    /**
     * 休憩開始時間が退勤時間より後の場合エラーになる
     */
    public function test_break_start_after_clock_out_is_invalid(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post('/attendance/detail/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['19:00'],
                'new_break_out' => ['19:30'],
                'comment' => '修正します',
            ]);

        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);
    }

    /**
     * 休憩終了時間が退勤時間より後の場合エラーになる
     */
    public function test_break_end_after_clock_out_is_invalid(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post('/attendance/detail/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['17:30'],
                'new_break_out' => ['18:30'],
                'comment' => '修正します',
            ]);

        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);
    }

    /**
     * 備考欄が未入力の場合エラーになる
     */
    public function test_comment_is_required(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post('/attendance/detail/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '',
            ]);

        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);
    }

    /**
     * 修正申請処理が実行される
     */
    public function test_user_can_submit_attendance_correction(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($user)
            ->post('/attendance/detail/' . $attendance->id, [
                'new_clock_in' => '08:30',
                'new_clock_out' => '17:30',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['12:45'],
                'comment' => '打刻時間を修正します',
            ]);

        $response->assertRedirect('/attendance/detail/' . $attendance->id);

        $this->assertDatabaseHas('attendance_corrections', [
            'attendance_id' => $attendance->id,
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => '打刻時間を修正します',
            'status' => 'pending',
        ]);

        $correction = AttendanceCorrection::first();

        $this->assertDatabaseHas('break_corrections', [
            'attendance_correction_id' => $correction->id,
            'break_start' => '12:00:00',
            'break_end' => '12:45:00',
        ]);
    }

    /**
     * 承認待ちに自分の申請が表示される
     */
    public function test_pending_corrections_are_displayed(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $attendance->attendanceCorrections()->create([
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => '承認待ちテスト',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSee('承認待ち');
        $response->assertSee('承認待ちテスト');
    }

    /**
     * 承認済みに管理者が承認した申請が表示される
     */
    public function test_approved_corrections_are_displayed(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $attendance->attendanceCorrections()->create([
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => '承認済みテスト',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSee('承認済み');
        $response->assertSee('承認済みテスト');
    }

    /**
     * 各申請の詳細から勤怠詳細画面へ遷移できる
     */
    public function test_correction_detail_link_goes_to_attendance_detail(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);

        $attendance->attendanceCorrections()->create([
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => '詳細リンクテスト',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSee('/attendance/detail/' . $attendance->id, false);
        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee('勤怠詳細');
        $response->assertSee('承認待ちのため修正できません');
    }
}