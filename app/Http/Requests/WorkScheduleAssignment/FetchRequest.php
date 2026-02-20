<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkScheduleAssignment;

use App\Models\WorkScheduleAssignment;
use App\Policies\WorkScheduleAssignmentPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * WorkScheduleAssignment lista lekérési kérés.
 */
class FetchRequest extends FormRequest
{
    /**
     * Jogosultság ellenőrzés.
     *
     * @return bool True, ha a felhasználó lekérheti a kiosztás listát.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(WorkScheduleAssignmentPolicy::PERM_VIEW_ANY, WorkScheduleAssignment::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:200'],
            'day' => ['nullable', 'date'],
            'field' => ['nullable', 'string', Rule::in(WorkScheduleAssignment::SORTABLE)],
            'order' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * PrimeVue DataTable paraméterek normalizálása.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $field = $this->input('field');
        $order = $this->input('order');

        if ($field === '') {
            $field = null;
        }
        if ($order === '') {
            $order = null;
        }

        $sortField = $this->input('sortField');
        $sortOrder = $this->input('sortOrder');

        if ($field === null && $sortField) {
            $field = $sortField;
        }

        if ($order === null && $sortOrder !== null) {
            if ($sortOrder === 1 || $sortOrder === '1') {
                $order = 'asc';
            }
            if ($sortOrder === -1 || $sortOrder === '-1') {
                $order = 'desc';
            }
        }

        $this->merge([
            'field' => $field,
            'order' => is_string($order) ? strtolower($order) : $order,
        ]);
    }

    /**
     * Validált és normalizált filter tömb előállítása.
     *
     * @return array{
     *   search: string|null,
     *   day: string|null,
     *   field: string|null,
     *   order: 'asc'|'desc'|null,
     *   page: int,
     *   per_page: int
     * }
     */
    public function validatedFilters(): array
    {
        $data = $this->validated();

        $search = $data['search'] ?? null;
        $search = is_string($search) ? trim($search) : null;
        if ($search === '' || $search === 'null' || $search === 'undefined') {
            $search = null;
        }

        return [
            'search' => $search,
            'day' => $data['day'] ?? null,
            'field' => $data['field'] ?? null,
            'order' => $data['order'] ?? null,
            'page' => (int) ($data['page'] ?? 1),
            'per_page' => (int) ($data['per_page'] ?? 10),
        ];
    }
}
