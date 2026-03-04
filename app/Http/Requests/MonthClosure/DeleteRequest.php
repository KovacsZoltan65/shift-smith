<?php

declare(strict_types=1);

namespace App\Http\Requests\MonthClosure;

use App\Http\Requests\MonthClosure\Concerns\ResolvesCurrentCompany;
use App\Models\MonthClosure;
use App\Policies\MonthClosurePolicy;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    use ResolvesCurrentCompany;

    public function authorize(): bool
    {
        return $this->user()?->can(MonthClosurePolicy::PERM_DELETE, MonthClosure::class) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
