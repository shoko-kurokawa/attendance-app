<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    //読み取り系API（認証不要）
    Route::get('/attendance-records', [AttendanceRecordController::class, 'index'])->name('api.v1.attendance-records.index');
    Route::get('/attendance-records/{id}', [AttendanceRecordController::class, 'show'])->name('api.v1.attendance-records.show');

    //書き込み系API
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/attendance-records', [AttendanceRecordController::class, 'store'])->name('api.v1.attendance-records.store');
        Route::put('/attendance-records/{id}', [AttendanceRecordController::class, 'update'])->name('api.v1.attendance-records.update');
        Route::delete('/attendance-records/{id}', [AttendanceRecordController::class, 'destroy'])->name('api.v1.attendance-records.destroy');
    });
});
