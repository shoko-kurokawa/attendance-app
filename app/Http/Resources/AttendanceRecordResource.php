<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'date' => $this->date?->format('Y-m-d'),
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'comment' => $this->comment,

            'user' => new UserResource($this->whenLoaded('user')),
            'breaks' => AttendanceBreakResource::collection($this->whenLoaded('breaks')),
            'applications' => AttendanceCorrectionResource::collection($this->whenLoaded('attendanceCorrections')),
        ];
    }
}
