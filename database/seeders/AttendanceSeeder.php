<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $user1 = User::where('email', 'user1@example.com')->first();
        $user2 = User::where('email', 'user2@example.com')->first();

        //ユーザー1の勤怠
        $attendance1 = $user1->attendances()->create([
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => null,
        ]);

        $attendance1->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        //ユーザー2の勤怠
        $attendance2 = $user2->attendances()->create([
            'date' => '2026-09-15',
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => null,
        ]);

        $attendance2->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '12:45:00',
        ]);
    }
}
