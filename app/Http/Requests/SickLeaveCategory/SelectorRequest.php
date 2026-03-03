<?php

declare(strict_types=1);

namespace App\Http\Requests\SickLeaveCategory;

use App\Models\SickLeaveCategory;
use App\Policies\SickLeaveCategoryPolicy;
use App\Support\CurrentCompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class SelectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(SickLeaveCategoryPolicy::PERM_VIEW_ANY, SickLeaveCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'only_active' => ['nullable', 'boolean'],
        ];
    }

    public function currentCompanyId(): int
    {
        return app(CurrentCompanyContext::class)->resolve($this);
    }

    public function onlyActive(): bool
    {
        return $this->boolean('only_active', true);
    }
}
