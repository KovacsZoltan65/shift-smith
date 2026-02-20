<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkShiftAssignment;

use App\Models\WorkShiftAssignment;
use App\Policies\WorkShiftAssigmentPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Műszak-hozzárendelés lista kérés validálása.
 */
class ListRequest extends FormRequest
{
    /**
     * Jogosultság ellenőrzés.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(WorkShiftAssigmentPolicy::PERM_VIEW_ANY, WorkShiftAssignment::class) ?? false;
    }

    /**
     * Validációs szabályok.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
