<?php

declare(strict_types=1);

namespace App\Http\Requests\Position;

use App\Models\Position;
use App\Policies\PositionPolicy;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PositionPolicy::PERM_DELETE_ANY, Position::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:positions,id'],
        ];
    }
}
