<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkPattern;

use App\Models\WorkPattern;
use App\Policies\WorkPatternPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(WorkPatternPolicy::PERM_CREATE, WorkPattern::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $companyId = (int) $this->input('company_id');

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('work_patterns', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'daily_work_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'core_start_time' => ['nullable', 'date_format:H:i:s', 'required_with:core_end_time'],
            'core_end_time' => ['nullable', 'date_format:H:i:s', 'required_with:core_start_time'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Validáció előtti normalizálás.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'active' => $this->has('active') ? $this->boolean('active') : true,
            'core_start_time' => $this->normalizeTime($this->input('core_start_time')),
            'core_end_time' => $this->normalizeTime($this->input('core_end_time')),
        ]);
    }

    private function normalizeTime(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return strlen($trimmed) === 5 ? "{$trimmed}:00" : $trimmed;
    }
}
