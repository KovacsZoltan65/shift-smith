<?php

declare(strict_types=1);

namespace App\Http\Requests\CompanySetting;

use App\Http\Requests\AppSetting\StoreRequest as BaseStoreRequest;
use App\Http\Requests\CompanySetting\Concerns\ResolvesCurrentCompany;
use App\Models\CompanySetting;
use App\Policies\CompanySettingPolicy;
use Illuminate\Validation\Rule;

class StoreRequest extends BaseStoreRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(CompanySettingPolicy::PERM_CREATE, CompanySetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => [
                'required',
                'string',
                'max:190',
                Rule::unique('company_settings', 'key')->where(fn ($query) => $query->where('company_id', $this->currentCompanyId())),
            ],
            'type' => ['required', 'string', 'in:int,bool,string,json'],
            'group' => ['required', 'string', 'max:100'],
            'label' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'value' => ['nullable'],
        ];
    }
}
