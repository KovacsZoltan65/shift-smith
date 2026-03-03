<?php

declare(strict_types=1);

namespace App\Http\Requests\LeaveCategory;

use App\Http\Requests\LeaveCategory\Concerns\ResolvesCurrentCompany;
use App\Models\LeaveCategory;
use App\Policies\LeaveCategoryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveCategoryRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(LeaveCategoryPolicy::PERM_CREATE, LeaveCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'active' => ['required', 'boolean'],
            'order_index' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function validatedPayload(): array
    {
        return $this->validated();
    }
}
