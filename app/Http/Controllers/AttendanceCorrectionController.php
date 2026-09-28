<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\AttendanceCorrection;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    public function store(AttendanceCorrectionRequest $request, $id): RedirectResponse
    {
        $user = $request->user();

        $attendance = $user->attendances()->with('breaks')->findOrFail($id);

        $attendanceCorrection = $attendance->attendanceCorrections()->create([
            'clock_in' => $request->input('new_clock_in'),
            'clock_out' => $request->input('new_clock_out'),
            'comment' => $request->input('comment'),
            'status' => 'pending',
        ]);

        $breakIns = $request->input('new_break_in', []);
        $breakOuts = $request->input('new_break_out', []);

        foreach ($breakIns as $index => $breakIn) {
            $breakOut = $breakOuts[$index] ?? null;

            if (empty($breakIn) && empty($breakOut)) {
                continue;
            }

            $originalBreak = $attendance->breaks->get($index);

            $attendanceCorrection->breakCorrections()->create([
                'break_id' => $originalBreak?->id,
                'break_start' => $breakIn,
                'break_end' => $breakOut,
            ]);
        }

        return redirect('/attendance/' . $attendance->id);
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $applications = AttendanceCorrection::whereHas(
            'attendance',
            function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }
        )
            ->with('attendance')->latest()->get();

        $formattedApplications = $applications->map(function ($application) {
            return [
                'id' => $application->attendance_id,
                'approval_status' => $application->status === 'pending'
                    ? '承認待ち'
                    : '承認済み',
                'date' => $application->attendance->date->format('Y/m/d'),
                'comment' => $application->comment,
                'application_date' => $application->created_at->format('Y/m/d'),
            ];
        });

        return view('user.user-application-list', compact(
            'user',
            'formattedApplications',
        ));
    }
}
