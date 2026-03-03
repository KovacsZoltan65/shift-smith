<?php

declare(strict_types=1);

namespace App\Http\Requests\LeaveCategory;

use App\Http\Requests\LeaveCategory\Concerns\ResolvesCurrentCompany;
use App\Models\LeaveCategory;
use App\Policies\LeaveCategoryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class SelectorRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(LeaveCategoryPolicy::PERM_VIEW_ANY, LeaveCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'only_active' => ['nullable', 'boolean'],
        ];
    }

    public function onlyActive(): bool
    {
        return $this->boolean('only_active', true);
    }
}
