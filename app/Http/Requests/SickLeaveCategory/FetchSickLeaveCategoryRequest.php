<?php

declare(strict_types=1);

namespace App\Http\Requests\SickLeaveCategory;

use App\Http\Requests\SickLeaveCategory\Concerns\ResolvesCurrentCompany;
use App\Models\SickLeaveCategory;
use App\Policies\SickLeaveCategoryPolicy;
use Illuminate\Foundation\Http\FormRequest;

class FetchSickLeaveCategoryRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(SickLeaveCategoryPolicy::PERM_VIEW_ANY, SickLeaveCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'active' => ['nullable', 'boolean'],
            'sortBy' => ['nullable', 'string', 'in:code,name,active,order_index,updated_at,created_at'],
            'sortDir' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function validatedFilters(): array
    {
        $data = $this->validated();

        return [
            'q' => isset($data['q']) && is_string($data['q']) ? trim($data['q']) : null,
            'active' => $data['active'] ?? null,
            'sortBy' => $data['sortBy'] ?? 'order_index',
            'sortDir' => $data['sortDir'] ?? 'asc',
            'page' => (int) ($data['page'] ?? 1),
            'perPage' => (int) ($data['perPage'] ?? 100),
        ];
    }
}
