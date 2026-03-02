<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use App\Models\Company;
use App\Policies\CompanyPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class SelectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(CompanyPolicy::PERM_VIEW, Company::class) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'only_with_employees' => ['nullable', 'boolean'],
        ];
    }
}
