<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkSchedule;

use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = (int) $this->route('id');
        $workSchedule = WorkSchedule::query()->find($id);

        return $workSchedule !== null
            && ($this->user()?->can(WorkSchedulePolicy::PERM_DELETE, $workSchedule) ?? false);
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ];
    }
}
