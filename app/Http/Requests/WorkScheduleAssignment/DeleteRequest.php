<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkScheduleAssignment;

use App\Models\WorkScheduleAssignment;
use App\Policies\WorkScheduleAssignmentPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * WorkScheduleAssignment törlési kérés.
 */
class DeleteRequest extends FormRequest
{
    /**
     * Jogosultság ellenőrzés.
     *
     * @return bool True, ha a felhasználó törölhet kiosztást.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(WorkScheduleAssignmentPolicy::PERM_DELETE, WorkScheduleAssignment::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
