<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAttendanceCorrectionController extends Controller
{
    public function index(): View
    {
        $applications = AttendanceCorrection::with(['attendance.user', 'breakCorrections',])->latest()->get();

        return view('admin.admin-application-list', compact('applications'));
    }

    public function show($id): View
    {
        $application = AttendanceCorrection::with(['attendance.user', 'breakCorrections',])->findOrFail($id);
        $user = $application->attendance->user;

        return view('admin.admin-application-detail', compact('application', 'user', ));
    }

    public function approve($id): RedirectResponse
    {
        $application = AttendanceCorrection::with(['attendance.breaks', 'breakCorrections',])->findOrFail($id);

        // 承認待ちの申請だけ処理
        if ($application->status !== 'pending') {
            return redirect('/stamp_correction_request/approve/' . $application->id);
        }

        $attendance = $application->attendance;

        // 出勤・退勤・備考を修正申請の内容に更新
        $attendance->update([
            'clock_in' => $application->clock_in,
            'clock_out' => $application->clock_out,
            'comment' => $application->comment,
        ]);

        // 休憩を修正申請の内容に更新
        foreach ($application->breakCorrections as $breakCorrection) {
            if ($breakCorrection->break_id) {
                // 既存の休憩を修正
                $break = $attendance->breaks()
                    ->find($breakCorrection->break_id);

                if ($break) {
                    $break->update([
                        'break_start' => $breakCorrection->break_start,
                        'break_end' => $breakCorrection->break_end,
                    ]);
                }
            } else {
                // break_id がない場合は追加された休憩
                $attendance->breaks()->create([
                    'break_start' => $breakCorrection->break_start,
                    'break_end' => $breakCorrection->break_end,
                ]);
            }
        }

        // 申請を承認済みにする
        $application->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return redirect('/stamp_correction_request/approve/' . $application->id);
    }
}
