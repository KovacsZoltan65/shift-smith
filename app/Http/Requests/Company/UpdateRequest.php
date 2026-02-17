<?php

namespace App\Http\Requests\Company;

use App\Models\Company;
use App\Policies\CompanyPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(CompanyPolicy::PERM_UPDATE, Company::class) ?? false;
    }
    
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rule>>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'name'    => ['required', 'string', 'max:120'],
            'email'   => [
                'nullable',
                'email:rfc,dns',
                'max:190',
                Rule::unique('companies', 'email')
                    ->ignore($id, 'id')
                    ->whereNull('deleted_at'),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:50'],
            'active'  => ['nullable', 'boolean'],
        ];
    }
    
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'   => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email'  => is_string($this->input('email')) ? trim($this->input('email')) : $this->input('email'),
            'phone'  => is_string($this->input('phone')) ? trim($this->input('phone')) : $this->input('phone'),
            'address'=> is_string($this->input('address')) ? trim($this->input('address')) : $this->input('address'),
            // ha küldve van, booleanizáljuk, ha nincs, nem piszkáljuk (marad a DB-ben)
            'active' => $this->has('active') ? $this->boolean('active') : $this->input('active'),
        ]);
    }
}