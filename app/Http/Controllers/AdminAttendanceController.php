<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminAttendanceRequest;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : today();

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $users = User::where('admin_status', false)->get();

        $attendanceRecords = Attendance::with('breaks')
            ->whereDate('date', $date)
            ->get();

        foreach ($attendanceRecords as $attendance) {
            $totalBreakSeconds = 0;

            foreach ($attendance->breaks as $break) {
                if ($break->break_start && $break->break_end) {
                    $totalBreakSeconds += Carbon::parse($break->break_start)
                        ->diffInSeconds(Carbon::parse($break->break_end));
                }
            }

            $attendance->total_break_time = $this->formatSeconds($totalBreakSeconds);

            if ($attendance->clock_in && $attendance->clock_out) {
                $workSeconds = Carbon::parse($attendance->clock_in)
                    ->diffInSeconds(Carbon::parse($attendance->clock_out));

                $attendance->total_time = $this->formatSeconds(
                    $workSeconds - $totalBreakSeconds
                );
            } else {
                $attendance->total_time = null;
            }
        }

        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords',
        ));
    }

    private function formatSeconds(int $seconds): ?string
    {
        if ($seconds <= 0) {
            return null;
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function show($id): View
    {
        $attendance = Attendance::with(['user', 'breaks'])
            ->findOrFail($id);

        $user = $attendance->user;

        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => Carbon::parse($attendance->date)->format('Y年'),
            'date' => Carbon::parse($attendance->date)->format('n月j日'),
            'clock_in' => $attendance->clock_in
                ? Carbon::parse($attendance->clock_in)->format('H:i')
                : '',
            'clock_out' => $attendance->clock_out
                ? Carbon::parse($attendance->clock_out)->format('H:i')
                : '',
            'breaks' => $attendance->breaks->map(function ($break) {
                return [
                    'break_in' => $break->break_start
                        ? Carbon::parse($break->break_start)->format('H:i')
                        : '',
                    'break_out' => $break->break_end
                        ? Carbon::parse($break->break_end)->format('H:i')
                        : '',
                ];
            })->toArray(),
            'comment' => $attendance->comment ?? '',
        ];

        return view('admin.admin-detail', compact(
            'user',
            'attendanceRecord',
        ));
    }

    public function update(AdminAttendanceRequest $request, $id): RedirectResponse
    {
        $attendance = Attendance::with('breaks')->findOrFail($id);

        $attendance->update([
            'clock_in' => $request->input('new_clock_in'),
            'clock_out' => $request->input('new_clock_out'),
            'comment' => $request->input('comment'),
        ]);

        $breakIns = $request->input('new_break_in', []);
        $breakOuts = $request->input('new_break_out', []);

        foreach ($breakIns as $index => $breakIn) {
            $breakOut = $breakOuts[$index] ?? null;

            if (empty($breakIn) && empty($breakOut)) {
                continue;
            }

            $existingBreak = $attendance->breaks->get($index);

            if ($existingBreak) {
                $existingBreak->update([
                    'break_start' => $breakIn,
                    'break_end' => $breakOut,
                ]);
            } else {
                $attendance->breaks()->create([
                    'break_start' => $breakIn,
                    'break_end' => $breakOut,
                ]);
            }
        }

        return redirect('/admin/attendance/' . $attendance->id);
    }
}

