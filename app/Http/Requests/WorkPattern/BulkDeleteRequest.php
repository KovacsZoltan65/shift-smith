<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkPattern;

use App\Models\WorkPattern;
use App\Policies\WorkPatternPolicy;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(WorkPatternPolicy::PERM_DELETE_ANY, WorkPattern::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:work_patterns,id'],
        ];
    }
}
