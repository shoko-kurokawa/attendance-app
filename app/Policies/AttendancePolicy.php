<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    /**
     * 勤怠を更新できるか判定
     */
    public function update(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id || $user->admin_status;
    }

    /**
     * 勤怠を削除できるか判定
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id || $user->admin_status;
    }
}
