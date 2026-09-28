<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user();

        $attendance = $user->attendances()
            ->whereDate('date', today())
            ->with('breaks')
            ->first();

        if (!$attendance) {
            $user->attendance_status = '勤務外';
        } elseif ($attendance->clock_out) {
            $user->attendance_status = '退勤済';
        } elseif ($attendance->breaks->whereNull('break_end')->isNotEmpty()) {
            $user->attendance_status = '休憩中';
        } else {
            $user->attendance_status = '出勤中';
        }

        $formattedDate = now()->isoFormat('YYYY年M月D日(ddd)');
        $formattedTime = now()->format('H:i');

        return view('user.attendance-register', compact(
            'user',
            'formattedDate',
            'formattedTime',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $action = $request->input('action');
        $attendance = $user->attendances()
            ->whereDate('date', today())
            ->with('breaks')
            ->first();

        switch ($action) {
            case 'clock_in':
                if (!$attendance) {
                    $user->attendances()->create([
                        'date' => today(),
                        'clock_in' => now()->format('H:i:s'),
                    ]);
                }
                break;

            case 'clock_out':
                if (
                    $attendance &&
                    !$attendance->clock_out &&
                    $attendance->breaks->whereNull('break_end')->isEmpty()
                ) {
                    $attendance->update([
                        'clock_out' => now()->format('H:i:s'),
                    ]);
                }
                break;

            case 'break_in':
                if (
                    $attendance &&
                    !$attendance->clock_out &&
                    $attendance->breaks->whereNull('break_end')->isEmpty()
                ) {
                    $attendance->breaks()->create([
                        'break_start' => now()->format('H:i:s'),
                    ]);
                }
                break;

            case 'break_out':
                if ($attendance && !$attendance->clock_out) {
                    $break = $attendance->breaks()
                        ->whereNull('break_end')
                        ->latest('id')
                        ->first();

                    if ($break) {
                        $break->update([
                            'break_end' => now()->format('H:i:s'),
                        ]);
                    }
                }
                break;
        }

        return redirect('/attendance');
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : now();

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendances = $user->attendances()
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString(),])
            ->with('breaks')
            ->get()
            ->keyBy(function ($attendance) {
                return $attendance->date->format('Y-m-d');
            });

        $formatAttendanceRecords = [];
        for (
            $currentDate = $startOfMonth->copy();
            $currentDate->lte($endOfMonth);
            $currentDate->addDay()
        ) {
            $attendance = $attendances->get($currentDate->format('Y-m-d'));

            $totalBreakSeconds = 0;
            $totalWorkSeconds = 0;

            if ($attendance) {
                foreach ($attendance->breaks as $break) {
                    if ($break->break_end) {
                        $breakStart = Carbon::parse($break->break_start);
                        $breakEnd = Carbon::parse($break->break_end);

                        $totalBreakSeconds += $breakStart->diffInSeconds($breakEnd);
                    }
                }
                if ($attendance->clock_out) {
                    $clockIn = Carbon::parse($attendance->clock_in);
                    $clockOut = Carbon::parse($attendance->clock_out);

                    $totalWorkSeconds = $clockIn->diffInSeconds($clockOut) - $totalBreakSeconds;
                }
            }

            $formattedAttendanceRecords[] = [
                'id' => $attendance?->id,
                'date' => $currentDate->isoFormat('MM/DD(ddd)'),
                'clock_in' => $attendance ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
                'clock_out' => $attendance?->clock_out ? Carbon::Parse($attendance->clock_in)->format('H:i') : '',
                'total_break_time' => $totalBreakSeconds > 0 ? gmdate('H:i:s', $totalBreakSeconds) : '',
                'total_time' => $totalWorkSeconds > 0 ? gmdate('H:i:s', $totalWorkSeconds) : '',
            ];
        }

        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords',
        ));
    }

    public function show(Request $request, $id): View
    {
        $user = $request->user();

        $attendance = $user->attendances()->with(['breaks', 'attendanceCorrections'])->findOrFail($id);

        $application = $attendance->attendanceCorrections()->where('status', 'pending')->latest('id')->first();

        $data = [
            'id' => $attendance->id,
            'year' => $attendance->date->format('Y年'),
            'date' => $attendance->date->format('n月j日'),
            'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
            'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
            'breaks' => $attendance->breaks->map(function ($break) {
                return [
                    'break_in' => $break->break_start ? Carbon::parse($break->break_start)->format('H:i') : '',
                    'break_out' => $break->break_end ? Carbon::parse($break->break_end)->format('H:i') : '',
                ];
            })->toArray(),
            'comment' => $attendance->comment ?? '',
            'application' => $application,
        ];

        return view('user.user-detail', compact(
            'user',
            'data',
        ));



    }
}
