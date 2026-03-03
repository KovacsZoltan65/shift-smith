<?php

declare(strict_types=1);

namespace App\Http\Requests\LeaveType;

use App\Http\Requests\LeaveType\Concerns\ResolvesCurrentCompany;
use App\Models\LeaveType;
use App\Policies\LeaveTypePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class FetchLeaveTypeRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    protected function prepareForValidation(): void
    {
        $categories = $this->input('category');

        if (is_string($categories)) {
            $categories = [$categories];
        }

        if ($categories !== null) {
            $categories = array_values(array_filter(
                Arr::wrap($categories),
                static fn (mixed $value): bool => is_string($value) && trim($value) !== ''
            ));
        }

        $this->merge([
            'category' => empty($categories) ? null : $categories,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can(LeaveTypePolicy::PERM_VIEW_ANY, LeaveType::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'array'],
            'category.*' => ['string', 'in:'.implode(',', LeaveType::getCategories())],
            'active' => ['nullable', 'boolean'],
            'sortBy' => ['nullable', 'string', 'in:code,name,category,active,updated_at,created_at'],
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
            'category' => isset($data['category']) && is_array($data['category']) ? array_values($data['category']) : null,
            'active' => $data['active'] ?? null,
            'sortBy' => $data['sortBy'] ?? 'name',
            'sortDir' => $data['sortDir'] ?? 'asc',
            'page' => (int) ($data['page'] ?? 1),
            'perPage' => (int) ($data['perPage'] ?? 10),
        ];
    }
}
