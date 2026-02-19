<?php

declare(strict_types=1);

namespace App\Http\Requests\EmployeeWorkPattern;

use App\Models\EmployeeWorkPattern;
use App\Policies\EmployeeWorkPatternPolicy;
use Illuminate\Foundation\Http\FormRequest;

class ListByEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(EmployeeWorkPatternPolicy::PERM_VIEW, EmployeeWorkPattern::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
