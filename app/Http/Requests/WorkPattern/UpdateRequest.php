<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkPattern;

use App\Models\WorkPattern;
use App\Policies\WorkPatternPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(WorkPatternPolicy::PERM_UPDATE, WorkPattern::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');
        $companyId = (int) $this->input('company_id');

        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('work_patterns', 'name')
                    ->ignore($id, 'id')
                    ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'type' => ['required', 'string', 'in:fixed_weekly,rotating_shifts,custom'],
            'cycle_length_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'weekly_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'active' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
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
            'type' => is_string($this->input('type')) ? trim($this->input('type')) : $this->input('type'),
            'active' => $this->has('active') ? $this->boolean('active') : $this->input('active'),
        ]);
    }
}
