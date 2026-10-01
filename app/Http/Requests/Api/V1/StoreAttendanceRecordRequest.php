<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'clock_in' => ['required', 'date_format:H:i:s'],
            'clock_out' => ['nullable', 'date_format:H:i:s'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'ユーザーIDを入力してください。',
            'user_id.integer' => 'ユーザーIDは整数で入力してください。',
            'user_id.exists' => '指定されたユーザーが存在しません。',

            'date.required' => '日付を入力してください。',
            'date.date' => '日付は正しい形式で入力してください。',

            'clock_in.required' => '出勤時間を入力してください。',
            'clock_in.date_format' => '出勤時間はHH:MM:SS形式で入力してください。',

            'clock_out.date_format' => '退勤時間はHH:MM:SS形式で入力してください。',

            'comment.string' => '備考は文字列で入力してください。',
            'comment.max' => '備考は255文字以内で入力してください。',
        ];
    }
}
