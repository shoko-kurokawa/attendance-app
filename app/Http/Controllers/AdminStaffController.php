<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;


class AdminStaffController extends Controller
{
    public function index(): View
    {
        $users = User::where('admin_status', false)->orderBy('id')->get();

        return view('admin.staff-list', compact('users'));
    }

    public function show(Request $request, $id): View
    {
        $user = User::where('admin_status', false)->findOrFail($id);

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : today();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $attendanceRecords = $user->attendances()->with('breaks')
            ->whereBetween('date', [
                $startOfMonth->format('Y-m-d'),
                $endOfMonth->format('Y-m-d'),
            ])
            ->get()
            ->keyBy(function ($attendance) {
                return Carbon::parse($attendance->date)->format('Y-m-d');
            });

        $formattedAttendanceRecords = collect();

        for (
            $currentDate = $startOfMonth->copy();
            $currentDate->lte($endOfMonth);
            $currentDate->addDay()
        ) {
            $dateKey = $currentDate->format('Y-m-d');
            $attendance = $attendanceRecords->get($dateKey);

            $totalBreakSeconds = 0;

            if ($attendance) {
                foreach ($attendance->breaks as $break) {
                    if ($break->break_start && $break->break_end) {
                        $totalBreakSeconds += Carbon::parse($break->break_start)
                            ->diffInSeconds(Carbon::parse($break->break_end));
                    }
                }
            }

            $totalWorkSeconds = null;

            if (
                $attendance &&
                $attendance->clock_in &&
                $attendance->clock_out
            ) {
                $totalSeconds = Carbon::parse($attendance->clock_in)
                    ->diffInSeconds(Carbon::parse($attendance->clock_out));

                $totalWorkSeconds = $totalSeconds - $totalBreakSeconds;
            }

            $formattedAttendanceRecords->push([
                'id' => $attendance?->id,

                'date' => $currentDate->isoFormat('MM/DD(ddd)'),

                'clock_in' => $attendance?->clock_in
                    ? Carbon::parse($attendance->clock_in)->format('H:i')
                    : '',

                'clock_out' => $attendance?->clock_out
                    ? Carbon::parse($attendance->clock_out)->format('H:i')
                    : '',

                'total_break_time' => $totalBreakSeconds > 0
                    ? $this->formatSeconds($totalBreakSeconds)
                    : null,

                'total_time' => $totalWorkSeconds !== null
                    ? $this->formatSeconds($totalWorkSeconds)
                    : null,
            ]);
        }

        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords',
        ));
    }

    private function formatSeconds(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
