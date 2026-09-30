<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ勤怠レポートを表示
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $currentMonth = today()->startOfMonth();
        $startMonth = $currentMonth->copy()->subMonth(5);

        $attendances = $user->attendances()
            ->with('breaks')
            ->whereBetween('date', [
                $startMonth->copy()->startOfMonth()->toDateString(),
                $currentMonth->copy()->endOfMonth()->toDateString(),
            ])
            ->orderBy('date')
            ->get();

        $totalWorkMinutes = 0;
        $totalOvertimeMinutes = 0;
        $workDays = 0;

        $monthlyTrend = collect();

        for ($i = 0; $i < 6; $i++) {
            $month = $startMonth->copy()->addMonths($i);

            $monthlyTrend->push([
                'month' => $month->format('Y/m'),
                'work_minutes' => 0,
                'overtime_minutes' => 0,
            ]);
        }

        $lateCount = 0;
        $earlyLeaveCount = 0;
        $longWorkCount = 0;

        foreach ($attendances as $attendance) {
            if (!$attendance->clock_in || !$attendance->clock_out) {
                continue;
            }

            $clockIn = Carbon::parse($attendance->clock_in);
            $clockOut = Carbon::parse($attendance->clock_out);

            $workMinutes = $clockIn->diffInMinutes($clockOut);

            foreach ($attendance->breaks as $break) {
                if ($break->break_start && $break->break_end) {
                    $breakStart = Carbon::parse($break->break_start);
                    $breakEnd = Carbon::parse($break->break_end);

                    $workMinutes -= $breakStart->diffInMinutes($breakEnd);
                }
            }

            $workMinutes = max(0, $workMinutes);

            $overtimeMinutes = max(0, $workMinutes - 480);

            $totalWorkMinutes += $workMinutes;
            $totalOvertimeMinutes += $overtimeMinutes;
            $workDays++;

            $attendanceMonth = Carbon::parse($attendance->date)->format('Y/m');

            $monthlyTrend = $monthlyTrend->map(function ($row) use ($attendanceMonth, $workMinutes, $overtimeMinutes) {
                if ($row['month'] === $attendanceMonth) {
                    $row['work_minutes'] += $workMinutes;
                    $row['overtime_minutes'] += $overtimeMinutes;
                }

                return $row;
            });

            $attendanceDate = Carbon::parse($attendance->date);

            if ($attendanceDate->isSameMonth($currentMonth)) {
                if ($clockIn->format('H:i') > '09:00') {
                    $lateCount++;
                }

                if ($clockOut->format('H:i') < '18:00') {
                    $earlyLeaveCount++;
                }

                if ($workMinutes > 600) {
                    $longWorkCount++;
                }
            }
        }

        $summary = [
            'total_work_minutes' => $totalWorkMinutes,
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'avg_work_minutes' => $workDays > 0
                ? (int) round($totalWorkMinutes / $workDays)
                : 0,
        ];

        $anomalies = [
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
            'long_work_count' => $longWorkCount,
        ];

        return view('reports.index', compact(
            'summary',
            'monthlyTrend',
            'anomalies',
        ));
    }
}
