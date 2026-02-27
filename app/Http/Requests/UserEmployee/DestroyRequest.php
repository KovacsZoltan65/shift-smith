<?php

declare(strict_types=1);

namespace App\Http\Requests\UserEmployee;

use Illuminate\Foundation\Http\FormRequest;

final class DestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('user_employees.delete') ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
