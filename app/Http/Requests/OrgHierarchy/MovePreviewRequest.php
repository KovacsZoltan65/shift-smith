<?php

declare(strict_types=1);

namespace App\Http\Requests\OrgHierarchy;

use App\Policies\OrgHierarchyPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(OrgHierarchyPolicy::PERM_VIEW_ANY) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'min:1', 'exists:companies,id'],
            'employee_id' => ['required', 'integer', 'min:1', 'exists:employees,id'],
            'new_supervisor_employee_id' => ['nullable', 'integer', 'min:1', 'exists:employees,id'],
            'mode' => ['required', Rule::in([
                'employee_only',
                'leader_without_subordinates',
                'leader_with_subordinates',
                'move_subordinates_only',
            ])],
            'subordinates_strategy' => ['nullable', Rule::in([
                'reassign_to_old_supervisor',
                'reassign_to_specific_supervisor',
                'keep_with_leader',
            ])],
            'target_supervisor_for_subordinates' => ['nullable', 'integer', 'min:1', 'exists:employees,id'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'at_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array{
     *   company_id:int,
     *   employee_id:int,
     *   new_supervisor_employee_id:int|null,
     *   mode:string,
     *   subordinates_strategy:string,
     *   target_supervisor_for_subordinates:int|null,
     *   effective_from:string,
     *   at_date:string
     * }
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        return [
            'company_id' => (int) $data['company_id'],
            'employee_id' => (int) $data['employee_id'],
            'new_supervisor_employee_id' => array_key_exists('new_supervisor_employee_id', $data) && $data['new_supervisor_employee_id'] !== null
                ? (int) $data['new_supervisor_employee_id']
                : null,
            'mode' => (string) $data['mode'],
            'subordinates_strategy' => (string) ($data['subordinates_strategy'] ?? 'reassign_to_old_supervisor'),
            'target_supervisor_for_subordinates' => array_key_exists('target_supervisor_for_subordinates', $data) && $data['target_supervisor_for_subordinates'] !== null
                ? (int) $data['target_supervisor_for_subordinates']
                : null,
            'effective_from' => (string) ($data['effective_from'] ?? now()->toDateString()),
            'at_date' => (string) ($data['at_date'] ?? now()->toDateString()),
        ];
    }
}
