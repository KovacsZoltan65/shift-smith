<?php

declare(strict_types=1);

namespace App\Http\Requests\OrgHierarchy;

use App\Policies\OrgHierarchyPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class EmployeeSearchRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array{company_id:int,q:string,limit:int}
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        return [
            'company_id' => (int) $data['company_id'],
            'q' => trim((string) ($data['q'] ?? '')),
            'limit' => (int) ($data['limit'] ?? 20),
        ];
    }
}
