<?php

declare(strict_types=1);

namespace App\Http\Requests\SickLeaveCategory;

use App\Http\Requests\SickLeaveCategory\Concerns\ResolvesCurrentCompany;
use App\Models\SickLeaveCategory;
use App\Policies\SickLeaveCategoryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class DeleteSickLeaveCategoryRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(SickLeaveCategoryPolicy::PERM_DELETE, SickLeaveCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
