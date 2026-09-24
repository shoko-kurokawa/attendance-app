<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user();

        $attendance = $user->attendances()->whereDate('date', today())->with('breaks')->first();

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
}
