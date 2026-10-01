<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * ゲストはマイレポートにアクセスできない
     */
    public function test_guest_cannot_access_attendance_report(): void
    {
        $response = $this->get('/attendance/report');
        $response->assertRedirect('/login');
    }

    /**
     * 認証ユーザーの勤怠統計が正しく計算される
     */
    public function test_authenticated_user_attendance_statistics_are_calculated_correctly(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 30, 12, 0, 0));

        $user = User::factory()->create();

        // 8月：通常勤務
        // 09:00〜18:00 - 休憩1時間 = 実労働8時間
        $this->createAttendanceWithBreak(
            $user,
            '2026-08-15',
            '09:00:00',
            '18:00:00'
        );

        // 9月：残業
        // 09:00〜20:00 - 休憩1時間 = 実労働10時間
        // 残業2時間
        $this->createAttendanceWithBreak(
            $user,
            '2026-09-10',
            '09:00:00',
            '20:00:00'
        );

        // 9月：遅刻
        // 09:30〜18:00 - 休憩1時間 = 実労働7時間30分
        $this->createAttendanceWithBreak(
            $user,
            '2026-09-11',
            '09:30:00',
            '18:00:00'
        );

        // 9月：早退
        // 09:00〜17:00 - 休憩1時間 = 実労働7時間
        $this->createAttendanceWithBreak(
            $user,
            '2026-09-12',
            '09:00:00',
            '17:00:00'
        );

        // 9月：長時間労働
        // 08:00〜21:00 - 休憩1時間 = 実労働12時間
        // 残業4時間・長時間労働1日
        $this->createAttendanceWithBreak(
            $user,
            '2026-09-13',
            '08:00:00',
            '21:00:00'
        );

        $response = $this->actingAs($user)->get('/attendance/report');
        $response->assertStatus(200);

        /*
         * 総労働時間
         * 8h + 10h + 7.5h + 7h + 12h
         * = 44.5h = 2670分
         *
         * 総残業時間
         * 2h + 4h = 6h = 360分
         *
         * 平均労働時間
         * 2670 ÷ 5 = 534分 = 8h54m
         */
        $response->assertViewHas('summary', [
            'total_work_minutes' => 2670,
            'total_overtime_minutes' => 360,
            'avg_work_minutes' => 534,
        ]);

        /*
         * 今月（2026年9月）の異常検知
         *
         * 遅刻：1回
         * 早退：1回
         * 長時間労働：1回
         */
        $response->assertViewHas('anomalies', [
            'late_count' => 1,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ]);

        /*
         * 月次推移
         *
         * 8月：8時間 = 480分 / 残業0分
         * 9月：36.5時間 = 2190分 / 残業360分
         */
        $response->assertViewHas('monthlyTrend', function ($monthlyTrend) {
            $august = $monthlyTrend->firstWhere('month', '2026/08');
            $september = $monthlyTrend->firstWhere('month', '2026/09');

            return $monthlyTrend->count() === 6
                && $august !== null
                && $august['work_minutes'] === 480
                && $august['overtime_minutes'] === 0
                && $september !== null
                && $september['work_minutes'] === 2190
                && $september['overtime_minutes'] === 360;
        });
    }

    /**
     * 勤怠記録がないユーザーでも安全にレポートを表示できる
     */
    public function test_user_without_attendance_records_can_view_report_safely(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 30, 12, 0, 0));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/attendance/report');
        $response->assertStatus(200);

        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);

        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);

        $response->assertViewHas('monthlyTrend', function ($monthlyTrend) {
            return $monthlyTrend->count() === 6
                && $monthlyTrend->every(function ($row) {
                    return $row['work_minutes'] === 0
                        && $row['overtime_minutes'] === 0;
                });
        });
    }

    /**
     * 勤怠と固定休憩を作成
     */
    private function createAttendanceWithBreak(
        User $user,
        string $date,
        string $clockIn,
        string $clockOut
    ): Attendance {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'comment' => null,
        ]);

        $attendance->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        return $attendance;
    }
}
