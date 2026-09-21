<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_correction_id',
        'break_id',
        'break_start',
        'break_end',
    ];

    public function attendanceCorrection(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrection::class);
    }

    public function attendanceBreak(): BelongsTo
    {
        return $this->belongsTo(AttendanceBreak::class, 'break_id');
    }

}
