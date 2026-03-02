<?php

declare(strict_types=1);

namespace App\Http\Requests\CompanySetting;

use App\Http\Requests\CompanySetting\Concerns\ResolvesCurrentCompany;
use App\Models\CompanySetting;
use App\Policies\CompanySettingPolicy;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(CompanySettingPolicy::PERM_DELETE_ANY, CompanySetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:company_settings,id'],
        ];
    }
}
