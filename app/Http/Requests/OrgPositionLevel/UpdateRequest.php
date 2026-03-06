<?php

declare(strict_types=1);

namespace App\Http\Requests\OrgPositionLevel;

use App\Models\Employee;
use App\Models\PositionOrgLevel;
use App\Policies\PositionOrgLevelPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PositionOrgLevelPolicy::PERM_UPDATE, PositionOrgLevel::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'position_label' => ['required', 'string', 'max:191'],
            'org_level' => ['required', 'string', Rule::in(Employee::ORG_LEVELS)],
            'active' => ['required', 'boolean'],
        ];
    }
}

