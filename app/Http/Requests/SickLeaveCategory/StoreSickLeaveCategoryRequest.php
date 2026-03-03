<?php

declare(strict_types=1);

namespace App\Http\Requests\SickLeaveCategory;

use App\Http\Requests\SickLeaveCategory\Concerns\ResolvesCurrentCompany;
use App\Models\SickLeaveCategory;
use App\Policies\SickLeaveCategoryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class StoreSickLeaveCategoryRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(SickLeaveCategoryPolicy::PERM_CREATE, SickLeaveCategory::class) ?? false;
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
