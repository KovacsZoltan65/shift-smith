<?php

declare(strict_types=1);

namespace App\Http\Requests\LeaveType;

use App\Http\Requests\LeaveType\Concerns\ResolvesCurrentCompany;
use App\Models\LeaveType;
use App\Policies\LeaveTypePolicy;
use Illuminate\Foundation\Http\FormRequest;

class FetchLeaveTypeRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(LeaveTypePolicy::PERM_VIEW_ANY, LeaveType::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'in:leave,sick_leave,paid_absence,unpaid_absence'],
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
            'category' => $data['category'] ?? null,
            'active' => $data['active'] ?? null,
            'sortBy' => $data['sortBy'] ?? 'name',
            'sortDir' => $data['sortDir'] ?? 'asc',
            'page' => (int) ($data['page'] ?? 1),
            'perPage' => (int) ($data['perPage'] ?? 10),
        ];
    }
}
