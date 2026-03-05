<?php

declare(strict_types=1);

namespace App\Http\Requests\MonthClosure;

use App\Http\Requests\MonthClosure\Concerns\ResolvesCurrentCompany;
use App\Models\MonthClosure;
use App\Policies\MonthClosurePolicy;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(MonthClosurePolicy::PERM_CREATE, MonthClosure::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
