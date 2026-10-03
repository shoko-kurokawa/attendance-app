<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 勤怠一覧をJSONで取得できる
     */
    public function test_attendance_records_can_be_listed(): void
    {
        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => null,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-16',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => null,
        ]);

        $response = $this->getJson('/api/v1/attendance-records');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'user_id',
                    'date',
                    'clock_in',
                    'clock_out',
                    'comment',
                    'user',
                    'breaks',
                    'applications',
                ],
            ],
            'links',
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 20);
        $response->assertJsonPath('meta.total', 2);
    }

    /**
     * 勤怠詳細をユーザー・休憩・修正申請を含めて取得できる
     */
    public function test_attendance_record_detail_can_be_retrieved(): void
    {
        $user = User::factory()->create(['name' => 'テストユーザー',]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);

        $attendance->breaks()->create([
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
        ]);

        AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'clock_in' => '09:30:00',
            'clock_out' => '18:00:00',
            'comment' => '修正申請',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/attendance-records/' . $attendance->id);

        $response->assertStatus(200);

        $response->assertJsonPath('data.id', $attendance->id);
        $response->assertJsonPath('data.user_id', $user->id);
        $response->assertJsonPath('data.date', '2026-09-15');
        $response->assertJsonPath('data.clock_in', '09:00:00');
        $response->assertJsonPath('data.clock_out', '18:00:00');

        $response->assertJsonStructure([
            'data' => [
                'id',
                'user_id',
                'date',
                'clock_in',
                'clock_out',
                'comment',
                'user',
                'breaks' => [
                    '*' => [
                        'id',
                        'break_start',
                        'break_end',
                    ],
                ],
                'applications',
            ],
        ]);

        $response->assertJsonCount(1, 'data.breaks');
        $response->assertJsonCount(1, 'data.applications');
    }

    /**
     * 存在しない勤怠IDでは404とエラーJSONが返る
     */
    public function test_nonexistent_attendance_record_returns_404(): void
    {
        $response = $this->getJson('/api/v1/attendance-records/99999');
        $response->assertStatus(404);
        $response->assertExactJson(['error' => '勤怠情報が見つかりませんでした。',]);
    }

    /**
     * 認証ユーザーは勤怠を作成できる
     */
    public function test_authenticated_user_can_create_attendance_record(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance-records', [
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'API作成テスト',
        ]);

        $response->assertStatus(201);

        $response->assertJsonPath('data.user_id', $user->id);
        $response->assertJsonPath('data.date', '2026-10-01');
        $response->assertJsonPath('data.clock_in', '09:00:00');
        $response->assertJsonPath('data.clock_out', '18:00:00');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'API作成テスト',
        ]);
    }

    /**
     * 必須項目がない場合は422と日本語エラーが返る
     */
    public function test_create_attendance_record_returns_422_for_invalid_data(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/v1/attendance-records',
            []
        );

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors',]);
        $response->assertJsonValidationErrors(['date', 'clock_in',]);
        $response->assertJsonPath('errors.date.0', '勤怠日は必須です。');
        $response->assertJsonPath('errors.clock_in.0', '出勤時刻は必須です。');
    }

    /**
     * 認証ユーザーは自分の勤怠を更新できる
     */
    public function test_authenticated_user_can_update_own_attendance_record(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '更新前',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson(
            '/api/v1/attendance-records/' . $attendance->id,
            [
                'clock_in' => '09:30:00',
                'clock_out' => '18:30:00',
                'comment' => '更新後',
            ]
        );

        $response->assertStatus(200);

        $response->assertJsonPath('data.clock_in', '09:30:00');
        $response->assertJsonPath('data.clock_out', '18:30:00');
        $response->assertJsonPath('data.comment', '更新後');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user->id,
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
            'comment' => '更新後',
        ]);
    }

    /**
     * 存在しない勤怠は更新できない
     */
    public function test_update_nonexistent_attendance_record_returns_404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson(
            '/api/v1/attendance-records/99999',
            [
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
                'comment' => '更新テスト',
            ]
        );

        $response->assertStatus(404);
        $response->assertExactJson(['error' => '勤怠情報が見つかりませんでした。',]);
    }

    /**
     * 認証ユーザーは自分の勤怠を削除できる
     */
    public function test_authenticated_user_can_delete_own_attendance_record(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/attendance-records/' . $attendance->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id,]);
    }

    /**
     * 存在しない勤怠は削除できない
     */
    public function test_delete_nonexistent_attendance_record_returns_404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/attendance-records/99999');
        $response->assertStatus(404);
        $response->assertExactJson(['error' => '勤怠情報が見つかりませんでした。',]);
    }

    /**
     * 未認証ユーザーは書き込み系APIを利用できない
     */
    public function test_unauthenticated_user_cannot_use_write_apis(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => null,
        ]);

        // POST
        $postResponse = $this->postJson('/api/v1/attendance-records', [
            'user_id' => $user->id,
            'date' => '2026-10-02',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $postResponse->assertStatus(401);
        $postResponse->assertExactJson(['message' => 'Unauthenticated.',]);

        // PUT
        $putResponse = $this->putJson(
            '/api/v1/attendance-records/' . $attendance->id,
            [
                'clock_in' => '09:30:00',
                'clock_out' => '18:00:00',
            ]
        );

        $putResponse->assertStatus(401);
        $putResponse->assertExactJson(['message' => 'Unauthenticated.',]);

        // DELETE
        $deleteResponse = $this->deleteJson(
            '/api/v1/attendance-records/' . $attendance->id
        );

        $deleteResponse->assertStatus(401);
        $deleteResponse->assertExactJson(['message' => 'Unauthenticated.',]);

        // 未認証操作によって元の勤怠が変更・削除されていない
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    /**
     * 他ユーザーの勤怠は更新できない
     */
    public function test_user_cannot_update_another_users_attendance_record(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user2->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '更新前',
        ]);

        Sanctum::actingAs($user1);

        $response = $this->putJson(
            '/api/v1/attendance-records/' . $attendance->id,
            [
                'clock_in' => '10:00:00',
                'clock_out' => '19:00:00',
                'comment' => '不正な更新',
            ]
        );

        $response->assertStatus(403);
        $response->assertExactJson(['error' => 'この操作を実行する権限がありません。',]);

        // 実際に更新されていないことも確認
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user2->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '更新前',
        ]);
    }

    /**
     * 他ユーザーの勤怠は削除できない
     */
    public function test_user_cannot_delete_another_users_attendance_record(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user2->id,
            'date' => '2026-10-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => null,
        ]);

        Sanctum::actingAs($user1);

        $response = $this->deleteJson('/api/v1/attendance-records/' . $attendance->id);
        $response->assertStatus(403);
        $response->assertExactJson(['error' => 'この操作を実行する権限がありません。',]);

        // 403なので削除されていない
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user2->id,
        ]);
    }

    /**
     * PATCHで自分の勤怠を部分更新できる
     */
    public function test_authenticated_user_can_partially_update_own_attendance_with_patch(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '変更前',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson(
            '/api/v1/attendance-records/' . $attendance->id,
            [
                'comment' => 'PATCHで変更',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.comment', 'PATCHで変更');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user->id,
            'date' => '2026-09-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'PATCHで変更',
        ]);
    }

    /**
     * 一覧取得でper_pageが100を超える場合は422を返す
     */
    public function test_attendance_record_index_returns_422_when_per_page_exceeds_100(): void
    {
        $response = $this->getJson('/api/v1/attendance-records?per_page=101');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page',]);
    }
}
