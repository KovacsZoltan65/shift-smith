<?php

declare(strict_types=1);

namespace App\Http\Requests\OrgHierarchy;

use App\Policies\OrgHierarchyPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class GraphRequest extends FormRequest
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
            'root_employee_id' => ['nullable', 'integer', 'min:1', 'exists:employees,id'],
            'at_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array{company_id:int,root_employee_id:int|null,at_date:string}
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        return [
            'company_id' => (int) $data['company_id'],
            'root_employee_id' => array_key_exists('root_employee_id', $data) && $data['root_employee_id'] !== null
                ? (int) $data['root_employee_id']
                : null,
            'at_date' => (string) ($data['at_date'] ?? now()->toDateString()),
        ];
    }
}
