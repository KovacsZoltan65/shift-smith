<?php

declare(strict_types=1);

namespace App\Http\Requests\CompanySetting;

use App\Http\Requests\CompanySetting\Concerns\ResolvesCurrentCompany;
use App\Models\CompanySetting;
use App\Policies\CompanySettingPolicy;
use Illuminate\Validation\Rule;

class UpdateRequest extends StoreRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(CompanySettingPolicy::PERM_UPDATE, CompanySetting::class) ?? false;
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'key' => [
                'required',
                'string',
                'max:190',
                Rule::unique('company_settings', 'key')
                    ->ignore($id, 'id')
                    ->where(fn ($query) => $query->where('company_id', $this->currentCompanyId())),
            ],
            'type' => ['required', 'string', 'in:int,bool,string,json'],
            'group' => ['required', 'string', 'max:100'],
            'label' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'value' => ['nullable'],
        ];
    }
}
