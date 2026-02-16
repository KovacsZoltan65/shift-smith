<?php

namespace App\Http\Requests\WorkSchedule;

use App\Models\WorkSchedule;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('work_schedules.deleteAny', WorkSchedule::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:work_schedules,id'],
        ];
    }
}
