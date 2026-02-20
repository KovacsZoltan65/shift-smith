<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkPattern;

use App\Models\WorkPattern;
use App\Policies\WorkPatternPolicy;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $id = (int) $this->route('id');
        $workPattern = WorkPattern::query()->find($id);

        return $workPattern !== null
            && ($this->user()?->can(WorkPatternPolicy::PERM_DELETE, $workPattern) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ];
    }
}
