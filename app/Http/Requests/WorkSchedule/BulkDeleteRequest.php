<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkSchedule;

use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(WorkSchedulePolicy::PERM_DELETE_ANY, WorkSchedule::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:work_schedules,id'],
        ];
    }
}
