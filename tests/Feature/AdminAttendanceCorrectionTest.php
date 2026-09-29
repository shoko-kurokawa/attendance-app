<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'admin_status' => true,
        ]);
    }

    private function createAttendance(User $user): Attendance
    {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正前',
        ]);

        $attendance->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        return $attendance;
    }

    /**
     * 承認待ちの修正申請が全て表示される
     */
    public function test_pending_corrections_are_displayed(): void
    {
        $admin = $this->createAdmin();

        $user1 = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => 'テスト花子',
            'admin_status' => false,
        ]);

        $attendance1 = $this->createAttendance($user1);
        $attendance2 = $this->createAttendance($user2);

        AttendanceCorrection::create([
            'attendance_id' => $attendance1->id,
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => '太郎の修正申請',
            'status' => 'pending',
        ]);

        AttendanceCorrection::create([
            'attendance_id' => $attendance2->id,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
            'comment' => '花子の修正申請',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSee('承認待ち');
        $response->assertSee('テスト太郎');
        $response->assertSee('太郎の修正申請');
        $response->assertSee('テスト花子');
        $response->assertSee('花子の修正申請');
    }

    /**
     * 承認済みの修正申請が全て表示される
     */
    public function test_approved_corrections_are_displayed(): void
    {
        $admin = $this->createAdmin();

        $user1 = User::factory()->create([
            'name' => '承認済み太郎',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => '承認済み花子',
            'admin_status' => false,
        ]);

        $attendance1 = $this->createAttendance($user1);
        $attendance2 = $this->createAttendance($user2);

        AttendanceCorrection::create([
            'attendance_id' => $attendance1->id,
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => '承認済み申請1',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        AttendanceCorrection::create([
            'attendance_id' => $attendance2->id,
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
            'comment' => '承認済み申請2',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/list');
        $response->assertStatus(200);
        $response->assertSee('承認済み');
        $response->assertSee('承認済み太郎');
        $response->assertSee('承認済み申請1');
        $response->assertSee('承認済み花子');
        $response->assertSee('承認済み申請2');
    }

    /**
     * 修正申請の詳細内容が正しく表示される
     */
    public function test_correction_detail_is_displayed_correctly(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => '詳細テスト太郎',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => '電車遅延のため修正',
            'status' => 'pending',
        ]);

        $correction->breakCorrections()->create([
            'break_id' => $attendance->breaks->first()->id,
            'break_start' => '11:30:00',
            'break_end' => '12:30:00',
        ]);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/approve/' . $correction->id);
        $response->assertStatus(200);
        $response->assertSee('詳細テスト太郎');
        $response->assertSee('2026年');
        $response->assertSee('9月15日');
        $response->assertSee('08:30');
        $response->assertSee('17:30');
        $response->assertSee('11:30');
        $response->assertSee('12:30');
        $response->assertSee('電車遅延のため修正');
        $response->assertSee('承認');
    }

    /**
     * 修正申請を承認すると勤怠情報が更新される
     */
    public function test_correction_is_approved_and_attendance_is_updated(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);
        $break = $attendance->breaks->first();

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => '承認テスト',
            'status' => 'pending',
        ]);

        $correction->breakCorrections()->create([
            'break_id' => $break->id,
            'break_start' => '11:30:00',
            'break_end' => '12:30:00',
        ]);

        $response = $this->actingAs($admin)->post('/stamp_correction_request/approve/' . $correction->id);
        $response->assertRedirect('/stamp_correction_request/approve/' . $correction->id);

        // 修正申請が承認済みになったことを確認
        $this->assertDatabaseHas('attendance_corrections', [
            'id' => $correction->id,
            'status' => 'approved',
        ]);

        // 勤怠本体が申請内容に更新されたことを確認
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => '承認テスト',
        ]);

        // 休憩も申請内容に更新されたことを確認
        $this->assertDatabaseHas('breaks', [
            'id' => $break->id,
            'attendance_id' => $attendance->id,
            'break_start' => '11:30:00',
            'break_end' => '12:30:00',
        ]);

        // approved_at も保存されていることを確認
        $correction->refresh();

        $this->assertEquals('approved', $correction->status);
        $this->assertNotNull($correction->approved_at);
    }
}