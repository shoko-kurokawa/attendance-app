<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * 自分が行った勤怠情報がすべて表示される
     */
    public function test_user_can_see_own_attendance_records(): void
    {
        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-11',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);

        $otherUser = User::factory()->create();

        Attendance::create([
            'user_id' => $otherUser->id,
            'date' => '2026-09-12',
            'clock_in' => '07:12:00',
            'clock_out' => '16:12:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list?date=2026-09');
        $response->assertStatus(200);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('10:00');
        $response->assertSee('19:00');
        $response->assertDontSee('07:12');
        $response->assertDontSee('16:12');
    }

    /**
     * 勤怠一覧画面に遷移した際、現在の月が表示される
     */
    public function test_current_month_is_displayed(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 9, 29, 10, 0, 0)
        );

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/attendance/list');
        $response->assertStatus(200);
        $response->assertSee('2026/09');
    }

    /**
     * 「前月」を押下した時に前月の情報が表示される
     */
    public function test_previous_month_is_displayed(): void
    {
        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-08-15',
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
        ]);

        // 前月リンクを押した状態をURLで再現
        $response = $this->actingAs($user)->get('/attendance/list?date=2026-08');
        $response->assertStatus(200);
        $response->assertSee('2026/08');
        $response->assertSee('08:30');
        $response->assertSee('17:30');
    }

    /**
     * 「翌月」を押下した時に翌月の情報が表示される
     */
    public function test_next_month_is_displayed(): void
    {
        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-15',
            'clock_in' => '11:00:00',
            'clock_out' => '20:00:00',
        ]);

        // 翌月リンクを押した状態をURLで再現
        $response = $this->actingAs($user)->get('/attendance/list?date=2026-10');
        $response->assertStatus(200);
        $response->assertSee('2026/10');
        $response->assertSee('11:00');
        $response->assertSee('20:00');
    }

    /**
     * 「詳細」を押下すると、その日の勤怠詳細画面に遷移する
     */
    public function test_detail_link_goes_to_attendance_detail(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // 一覧画面に詳細リンクが存在することを確認
        $response = $this->actingAs($user)->get('/attendance/list?date=2026-09');
        $response->assertStatus(200);
        $response->assertSee('/attendance/detail/' . $attendance->id, false);

        // 詳細リンクの遷移先そのものも確認
        $response = $this->actingAs($user)->get('/attendance/detail/' . $attendance->id);
        $response->assertStatus(200);
        $response->assertSee('勤怠詳細');
    }
}
