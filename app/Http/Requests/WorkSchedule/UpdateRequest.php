<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkSchedule;

use App\Models\WorkSchedule;
use App\Policies\WorkSchedulePolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = (int) $this->route('id');
        $workSchedule = WorkSchedule::query()->find($id);

        return $workSchedule !== null
            && ($this->user()?->can(WorkSchedulePolicy::PERM_UPDATE, $workSchedule) ?? false);
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:150'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'status' => ['required', 'string', 'in:draft,published'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'status' => is_string($this->input('status')) ? trim($this->input('status')) : $this->input('status'),
        ]);
    }
}
