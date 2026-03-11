<?php

declare(strict_types=1);

namespace App\Http\Requests\Hq;

use App\Models\Company;
use App\Policies\HqCompanyPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class HqCompanyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(HqCompanyPolicy::PERM_UPDATE) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'tenant_group_id' => [
                'required',
                'integer',
                Rule::exists('tenant_groups', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:190',
                Rule::unique('companies', 'email')
                    ->ignore($id, 'id')
                    ->whereNull('deleted_at'),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tenant_group_id' => $this->input('tenant_group_id'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? trim($this->input('email')) : $this->input('email'),
            'phone' => is_string($this->input('phone')) ? trim($this->input('phone')) : $this->input('phone'),
            'address' => is_string($this->input('address')) ? trim($this->input('address')) : $this->input('address'),
            'active' => $this->has('active') ? $this->boolean('active') : $this->input('active'),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // TODO: allow controlled TenantGroup reassignment once the dedicated reassignment flow is implemented.
            $companyId = (int) $this->route('id');
            $company = Company::query()->find($companyId);

            if ($company === null) {
                return;
            }

            if ((int) $this->input('tenant_group_id') !== (int) $company->tenant_group_id) {
                $validator->errors()->add('tenant_group_id', __('companies.hq.validation.reassignment_not_supported'));
            }
        });
    }
}
