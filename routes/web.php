<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminStaffController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\AdminAttendanceCorrectionController;
use App\Http\Controllers\AdminAttendanceController;

Route::redirect('/', '/login');

Route::middleware('auth')->group(function () {
    //勤怠登録
    Route::get('/attendance', [AttendanceController::class, 'create'])->name('attendance');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

    //勤怠一覧
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.index');

    //勤怠詳細
    Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->name('attendance.show');

    //修正申請
    Route::post('/attendance/{id}', [AttendanceCorrectionController::class, 'store'])->name('attendance.corrections.store');

    //申請一覧
    Route::get('/stamp_correction_request/list', [AttendanceCorrectionController::class, 'index'])->name('attendance.correction.index');

});

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    //勤怠・一般ユーザー一覧
    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index'])->name('admin.attendance.index');
    Route::get('/admin/staff/list', [AdminStaffController::class, 'index'])->name('admin.staff.index');

    //ユーザー別月次勤怠
    Route::get('/admin/attendance/staff/{id}', [AdminStaffController::class, 'show'])->name('admin.staff.attendance');

    //個別の勤怠詳細・修正
    Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'show'])->name('admin.attendance.show');
    Route::post('/admin/attendance/{id}', [AdminAttendanceController::class, 'update'])->name('admin.attendance.update');

    //勤怠の承認
    Route::get('/stamp_correction_request/approve/{id}', [AdminAttendanceCorrectionController::class, 'show'])->name('admin.correction.show');
    Route::post('/stamp_correction_request/approve/{id}', [AdminAttendanceCorrectionController::class, 'approve'])->name('admin.correction.approve');
});