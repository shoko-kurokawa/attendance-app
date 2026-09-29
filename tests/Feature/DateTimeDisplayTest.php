<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateTimeDisplayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 現在の日時情報がUIと同じ形式で表示される
     */
    public function test_current_date_and_time_are_displayed(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 10, 30, 0));

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee(now()->isoFormat('YYYY年M月D日(ddd)'));
        $response->assertSee(now()->format('H:i'));

        Carbon::setTestNow();
    }
}
