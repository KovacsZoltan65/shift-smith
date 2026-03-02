<?php

declare(strict_types=1);

namespace App\Http\Requests\CompanySetting;

use App\Http\Requests\CompanySetting\Concerns\ResolvesCurrentCompany;
use App\Models\CompanySetting;
use App\Policies\CompanySettingPolicy;
use Illuminate\Foundation\Http\FormRequest;

class EffectiveRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(CompanySettingPolicy::PERM_VIEW_ANY, CompanySetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'keys' => ['nullable', 'array'],
            'keys.*' => ['string', 'max:190'],
            'group' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
