<?php

declare(strict_types=1);

namespace App\Http\Requests\OrgPositionLevel;

use App\Models\PositionOrgLevel;
use App\Policies\PositionOrgLevelPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PositionOrgLevelPolicy::PERM_VIEW_ANY, PositionOrgLevel::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'org_level' => ['nullable', 'string', Rule::in(\App\Models\Employee::ORG_LEVELS)],
            'active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

