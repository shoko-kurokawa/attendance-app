<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

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
}
