<?php

declare(strict_types=1);

namespace App\Http\Requests\OrgHierarchy;

use App\Policies\OrgHierarchyPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class DesignSettingsSaveRequest extends FormRequest
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
            'view_mode' => ['required', 'in:explorer,network'],
            'density' => ['required', 'in:compact,comfortable'],
            'show_position' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{company_id:int,view_mode:string,density:string,show_position:bool}
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();

        return [
            'company_id' => (int) $validated['company_id'],
            'view_mode' => (string) $validated['view_mode'],
            'density' => (string) $validated['density'],
            'show_position' => (bool) $validated['show_position'],
        ];
    }
}

