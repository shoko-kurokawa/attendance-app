<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCsvExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 選択した月の勤怠csvをダウンロードできる
     */
    public function test_admin_can_download_monthly_attendance_csv(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => null,
        ]);

        $response = $this->actingAs($admin)
            ->post('/export', [
                'user_id' => $user->id,
                'year_month' => '2026-09',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');
    }

    /**
     * CSVに勤怠時間・休憩時間・実労働時間が正しく出力される
     */
    public function test_csv_contains_correct_attendance_data(): void
    {
        $admin = User::factory()->create(['admin_status' => true,]);

        $user = User::factory()->create(['admin_status' => false,]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => null,
        ]);

        $attendance->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->post('/export', [
                'user_id' => $user->id,
                'year_month' => '2026-09',
            ]);

        $response->assertStatus(200);

        $content = $response->streamedContent();

        $this->assertStringContainsString('日付,出勤,退勤,休憩,合計', $content);
        $this->assertStringContainsString('2026/09/15,09:00,18:00,01:00,08:00', $content);
    }

    /**
     * 選択した月以外の勤怠はCSVに含まれない
     */
    public function test_csv_contains_only_selected_month_attendance(): void
    {
        $admin = User::factory()->create(['admin_status' => true,]);

        $user = User::factory()->create(['admin_status' => false,]);

        // 選択月
        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => null,
        ]);

        // 選択月以外
        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-08-20',
            'clock_in' => '10:00:00',
            'clock_out' => '17:00:00',
            'comment' => null,
        ]);

        $response = $this->actingAs($admin)
            ->post('/export', [
                'user_id' => $user->id,
                'year_month' => '2026-09',
            ]);

        $response->assertStatus(200);

        $content = $response->streamedContent();

        // 9月の勤怠は含まれる
        $this->assertStringContainsString('2026/09/15', $content);

        // 8月の勤怠は含まれない
        $this->assertStringNotContainsString('2026/08/20', $content);
    }
}
