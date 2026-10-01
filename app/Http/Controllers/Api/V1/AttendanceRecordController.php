<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\Attendance;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AttendanceRecordController extends Controller
{
    //勤怠一覧を取得
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Attendance::query()
            ->with(['user', 'breaks', 'attendanceCorrections'])
            ->orderByDesc('date');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->input('date'));
        }

        if ($request->filled('month')) {
            $query->whereYear('date', substr($request->input('month'), 0, 4))
                ->whereMonth('date', substr($request->input('month'), 5, 2));
        }

        $perPage = min(
            max((int) $request->input('per_page', 20), 1),
            100
        );

        $attendances = $query->paginate($perPage);

        return AttendanceRecordResource::collection($attendances);
    }

    //勤怠詳細を取得
    public function show(int $id): AttendanceRecordResource|JsonResponse
    {
        $attendance = Attendance::with([
            'user',
            'breaks',
            'attendanceCorrections',
        ])->find($id);

        if (!$attendance) {
            return response()->json([
                'error' => '勤怠情報が見つかりませんでした。',
            ], 404);
        }

        return new AttendanceRecordResource($attendance);
    }

    //勤怠を作成
    public function store(
        StoreAttendanceRecordRequest $request
    ): JsonResponse {
        $attendance = Attendance::create($request->validated());

        $attendance->load([
            'user',
            'breaks',
            'attendanceCorrections',
        ]);

        return (new AttendanceRecordResource($attendance))
            ->response()
            ->setStatusCode(201);
    }

    //勤怠を更新
    public function update(
        UpdateAttendanceRecordRequest $request,
        int $id
    ): AttendanceRecordResource|JsonResponse {
        $attendance = Attendance::find($id);

        if (!$attendance) {
            return response()->json([
                'error' => '勤怠情報が見つかりませんでした。',
            ], 404);
        }

        if (Gate::denies('update', $attendance)) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        $attendance->update($request->validated());

        $attendance->load([
            'user',
            'breaks',
            'attendanceCorrections',
        ]);

        return new AttendanceRecordResource($attendance);
    }

    //勤怠を削除
    public function destroy(int $id): Response|JsonResponse
    {
        $attendance = Attendance::find($id);

        if (!$attendance) {
            return response()->json([
                'error' => '勤怠情報が見つかりませんでした。',
            ], 404);
        }

        if (Gate::denies('delete', $attendance)) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        $attendance->delete();

        return response()->noContent();
    }
}
