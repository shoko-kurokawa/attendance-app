<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->first();
        $user2 = User::where('email', 'user2@example.com')->first();
        $user3 = User::where('email', 'user3@example.com')->first();

        $currentMonth = today()->startOfMonth();

        /**
         * ユーザー1の意図的データ
         */

        //過去5ヶ月：各月の平日15日を通常勤務 09:00〜18:00
        for ($monthOffset = 5; $monthOffset >= 1; $monthOffset--) {
            $month = $currentMonth->copy()->subMonths($monthOffset);

            $workDays = $this->getWeekdays($month, 15);

            foreach ($workDays as $date) {
                $this->createAttendance(
                    $user1,
                    $date,
                    '09:00:00',
                    '18:00:00'
                );
            }
        }

        //当月17日のパターン
        $currentWorkDays = $this->getWeekdays($currentMonth, 17);

        for ($i = 0; $i < 10; $i++) {
            $this->createAttendance(
                $user1,
                $currentWorkDays[$i],
                '09:00:00',
                '18:00:00'
            );
        }

        for ($i = 10; $i < 13; $i++) {
            $this->createAttendance(
                $user1,
                $currentWorkDays[$i],
                '09:00:00',
                '20:00:00'
            );
        }

        for ($i = 13; $i < 15; $i++) {
            $this->createAttendance(
                $user1,
                $currentWorkDays[$i],
                '09:30:00',
                '18:00:00'
            );
        }

        $this->createAttendance(
            $user1,
            $currentWorkDays[15],
            '09:00:00',
            '17:00:00'
        );

        $this->createAttendance(
            $user1,
            $currentWorkDays[16],
            '08:00:00',
            '21:00:00'
        );

        /**
         * ユーザー2の勤怠（実運用を想定）
         */

        $user2WorkDays = $this->getWeekdays(
            $currentMonth->copy()->subMonth(),
            10
        );

        foreach ($user2WorkDays as $date) {
            $this->createAttendance(
                $user2,
                $date,
                '08:30:00',
                '17:30:00'
            );
        }

        /**
         * ユーザー3の勤怠（管理者）
         */
        $user3WorkDays = $this->getWeekdays(
            $currentMonth->copy()->subMonth(),
            5
        );

        foreach ($user3WorkDays as $date) {
            $this->createAttendance(
                $user3,
                $date,
                '09:00:00',
                '18:00:00'
            );
        }
    }

    //指定月から平日のみ取得
    private function getWeekdays(Carbon $month, int $count): array
    {
        $dates = [];
        $date = $month->copy()->startOfMonth();

        while (
            count($dates) < $count &&
            $date->lte($month->copy()->endOfMonth())
        ) {
            if ($date->isWeekday()) {
                $dates[] = $date->copy();
            }

            $date->addDay();
        }

        return $dates;
    }

    //勤怠と固定休憩作成
    private function createAttendance(
        User $user,
        Carbon $date,
        string $clockIn,
        string $clockOut
    ): void {
        $attendance = $user->attendances()->create([
            'date' => $date->format('Y-m-d'),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'comment' => null,
        ]);

        $attendance->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);
    }
}
