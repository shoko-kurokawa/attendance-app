<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\Attendance;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AttendanceRecordController extends Controller
{
    //勤怠一覧を取得
    public function index(
        IndexAttendanceRecordRequest $request
    ): AnonymousResourceCollection {
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

        $perPage = (int) $request->input('per_page', 20);

        $attendances = $query->paginate($perPage);

        return AttendanceRecordResource::collection($attendances);
    }

    //勤怠詳細を取得
    public function show(
        Attendance $attendanceRecord
    ): AttendanceRecordResource|JsonResponse {
        $attendanceRecord->load([
            'user',
            'breaks',
            'attendanceCorrections',
        ]);

        return new AttendanceRecordResource($attendanceRecord);
    }

    //勤怠を作成
    public function store(
        StoreAttendanceRecordRequest $request
    ): JsonResponse {
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;

        $attendance = Attendance::create($validated);

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
        Attendance $attendanceRecord
    ): AttendanceRecordResource|JsonResponse {
        if (Gate::denies('update', $attendanceRecord)) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        $attendanceRecord->update($request->validated());

        $attendanceRecord->load([
            'user',
            'breaks',
            'attendanceCorrections',
        ]);

        return new AttendanceRecordResource($attendanceRecord);
    }

    //勤怠を削除
    public function destroy(
        Attendance $attendanceRecord
    ): Response|JsonResponse {
        if (Gate::denies('delete', $attendanceRecord)) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません。',
            ], 403);
        }

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
