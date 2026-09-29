<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
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
            'comment' => '通常勤務',
        ]);

        $attendance->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        return $attendance;
    }

    /**
     * 勤怠詳細画面に選択した勤怠情報が表示される
     */
    public function test_selected_attendance_is_displayed_correctly(): void
    {
        $admin = $this->createAdmin();

        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($admin)->get('/admin/attendance/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee('テスト太郎');
        $response->assertSee('2026年');
        $response->assertSee('9月15日');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:00');
        $response->assertSee('13:00');
        $response->assertSee('通常勤務');
    }

    /**
     * 出勤時間が退勤時間より後の場合エラーになる
     */
    public function test_clock_in_after_clock_out_is_invalid(): void
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();

        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/' . $attendance->id, [
                'new_clock_in' => '19:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '修正テスト',
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
        $admin = $this->createAdmin();
        $user = User::factory()->create();

        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['19:00'],
                'new_break_out' => ['19:30'],
                'comment' => '修正テスト',
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
        $admin = $this->createAdmin();
        $user = User::factory()->create();

        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/' . $attendance->id, [
                'new_clock_in' => '09:00',
                'new_clock_out' => '18:00',
                'new_break_in' => ['17:30'],
                'new_break_out' => ['18:30'],
                'comment' => '修正テスト',
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
        $admin = $this->createAdmin();
        $user = User::factory()->create();

        $attendance = $this->createAttendance($user);

        $response = $this->actingAs($admin)
            ->post('/admin/attendance/' . $attendance->id, [
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
}