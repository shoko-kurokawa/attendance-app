<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_clock_in' => [
                'required',
                'date_format:H:i',
            ],
            'new_clock_out' => [
                'required',
                'date_format:H:i',
                'after:new_clock_in',
            ],

            'new_break_in' => [
                'nullable',
                'array',
            ],
            'new_break_in.*' => [
                'nullable',
                'date_format:H:i',
                'after_or_equal:new_clock_in',
                'before_or_equal:new_clock_out',
            ],

            'new_break_out' => [
                'nullable',
                'array',
            ],
            'new_break_out.*' => [
                'nullable',
                'date_format:H:i',
                'before_or_equal:new_clock_out',
            ],

            'comment' => [
                'required',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',

            'new_break_in.*.date_format' => '休憩時間が不適切な値です',
            'new_break_in.*.after_or_equal' => '休憩時間が不適切な値です',
            'new_break_in.*.before_or_equal' => '休憩時間が不適切な値です',

            'new_break_out.*.date_format' => '休憩時間もしくは退勤時間が不適切な値です',
            'new_break_out.*.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',

            'comment.required' => '備考を記入してください',
        ];
    }
}
