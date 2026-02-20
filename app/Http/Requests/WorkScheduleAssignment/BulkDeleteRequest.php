<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkScheduleAssignment;

use App\Models\WorkScheduleAssignment;
use App\Policies\WorkScheduleAssignmentPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * WorkScheduleAssignment tömeges törlési kérés.
 */
class BulkDeleteRequest extends FormRequest
{
    /**
     * Jogosultság ellenőrzés.
     *
     * @return bool True, ha a felhasználó bulk törlést végezhet.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(WorkScheduleAssignmentPolicy::PERM_BULK_DELETE, WorkScheduleAssignment::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * Validált ID lista visszaadása integerként.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $this->validated('ids', []));
        return $ids;
    }
}
