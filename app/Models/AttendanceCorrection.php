<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'clock_in',
        'clock_out',
        'comment',
        'status',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime'
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function breakCorrections(): HasMany
    {
        return $this->hasMany(BreakCorrection::class);
    }

    //提供Blade用：pending / approved を日本語表示に変換
    public function getApprovalStatusAttribute(): string
    {
        return $this->status === 'pending'
            ? '承認待ち'
            : '承認済み';
    }

    //提供Blade用：申請日時として created_at を使用
    public function getApplicationDateAttribute()
    {
        return $this->created_at;
    }

    //提供Blade用：修正申請に紐づくユーザーを取得
    public function getUserAttribute()
    {
        return $this->attendance->user;
    }

    //提供Blade用：AttendanceRecord として attendance を取得
    public function getAttendanceRecordAttribute()
    {
        return $this->attendance;
    }

    // 提供Blade用：対象日
    public function getNewDateAttribute()
    {
        return $this->attendance->date;
    }

    // 提供Blade用：申請後の出勤時間
    public function getNewClockInAttribute()
    {
        return $this->clock_in;
    }

    // 提供Blade用：申請後の退勤時間
    public function getNewClockOutAttribute()
    {
        return $this->clock_out;
    }

    // 提供Blade用：申請された休憩情報
    public function getProposalBreaksAttribute()
    {
        return $this->breakCorrections;
    }
}
