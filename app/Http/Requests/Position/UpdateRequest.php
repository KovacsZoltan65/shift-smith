<?php

declare(strict_types=1);

namespace App\Http\Requests\Position;

use App\Models\Position;
use App\Policies\PositionPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PositionPolicy::PERM_UPDATE, Position::class) ?? false;
    }

    /**
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
                Rule::unique('positions', 'name')
                    ->ignore($id, 'id')
                    ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
            'active' => $this->has('active') ? $this->boolean('active') : $this->input('active'),
        ]);
    }
}
