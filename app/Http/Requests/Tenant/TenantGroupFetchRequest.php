<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\TenantGroup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A TenantGroup datatable landlord oldali szűrőparamétereit validálja.
 */
final class TenantGroupFetchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TenantGroup::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:50'],
            'sort_field' => ['nullable', 'string', 'in:id,name,code,status,active,created_at,updated_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.string' => __('validation.string'),
            'search.max' => __('validation.max.string'),
            'active.boolean' => __('validation.boolean'),
            'status.string' => __('validation.string'),
            'status.max' => __('validation.max.string'),
            'sort_field.string' => __('validation.string'),
            'sort_field.in' => __('validation.in'),
            'sort_direction.string' => __('validation.string'),
            'sort_direction.in' => __('validation.in'),
            'page.integer' => __('validation.integer'),
            'page.min' => __('validation.min.numeric'),
            'per_page.integer' => __('validation.integer'),
            'per_page.min' => __('validation.min.numeric'),
            'per_page.max' => __('validation.max.numeric'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $active = $this->input('active');

        // A PrimeVue szűrők a null és boolean értékeket gyakran stringként küldik,
        // itt alakítjuk őket stabil típusokra, hogy a repository egységes inputot kapjon.
        if ($active === '' || $active === 'null') {
            $active = null;
        } elseif ($active !== null) {
            $active = filter_var($active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $this->merge([
            'search' => \is_string($this->input('search')) ? trim((string) $this->input('search')) : $this->input('search'),
            'status' => \is_string($this->input('status')) ? trim((string) $this->input('status')) : $this->input('status'),
            'sort_field' => $this->input('sort_field') ?: 'created_at',
            'sort_direction' => strtolower((string) ($this->input('sort_direction') ?: 'desc')),
            'active' => $active,
        ]);
    }

    /**
     * @return array{
     *   search:?string,
     *   active:?bool,
     *   status:?string,
     *   sort_field:string,
     *   sort_direction:'asc'|'desc',
     *   page:int,
     *   per_page:int
     * }
     */
    public function validatedFilters(): array
    {
        $data = $this->validated();

        return [
            'search' => $data['search'] ?? null,
            'active' => $data['active'] ?? null,
            'status' => $data['status'] ?? null,
            'sort_field' => $data['sort_field'] ?? 'created_at',
            'sort_direction' => $data['sort_direction'] ?? 'desc',
            'page' => (int) ($data['page'] ?? 1),
            'per_page' => (int) ($data['per_page'] ?? 10),
        ];
    }
}
