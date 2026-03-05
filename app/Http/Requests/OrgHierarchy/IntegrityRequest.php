<?php

declare(strict_types=1);

namespace App\Http\Requests\OrgHierarchy;

use App\Policies\OrgHierarchyPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class IntegrityRequest extends FormRequest
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
            'at_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array{company_id:int,at_date:string}
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        return [
            'company_id' => (int) $data['company_id'],
            'at_date' => (string) ($data['at_date'] ?? now()->toDateString()),
        ];
    }
}
