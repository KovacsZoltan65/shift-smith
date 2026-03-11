<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use App\Policies\EmployeePolicy;
use App\Services\EmployeeTransfer\EmployeeTransferFormat;
use Illuminate\Foundation\Http\FormRequest;

final class EmployeeTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'format' => (string) $this->route('format'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can(EmployeePolicy::PERM_VIEW_ANY, Employee::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:'.implode(',', EmployeeTransferFormat::ALL)],
        ];
    }

    public function requestedFormat(): string
    {
        return EmployeeTransferFormat::normalize((string) $this->validated('format'));
    }
}
