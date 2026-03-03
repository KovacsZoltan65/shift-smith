<?php

declare(strict_types=1);

namespace App\Http\Requests\LeaveCategory;

use App\Http\Requests\LeaveCategory\Concerns\ResolvesCurrentCompany;
use App\Models\LeaveCategory;
use App\Policies\LeaveCategoryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class DeleteLeaveCategoryRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(LeaveCategoryPolicy::PERM_DELETE, LeaveCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
