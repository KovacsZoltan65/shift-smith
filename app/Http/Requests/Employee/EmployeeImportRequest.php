<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use App\Policies\EmployeePolicy;
use App\Services\EmployeeTransfer\EmployeeTransferFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

final class EmployeeImportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'format' => (string) $this->route('format'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can(EmployeePolicy::PERM_CREATE, Employee::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:'.implode(',', EmployeeTransferFormat::ALL)],
            'file' => ['required', 'file', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');

            if (! $file instanceof UploadedFile) {
                return;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            $expected = $this->requestedFormat();

            $allowedExtensions = match ($expected) {
                EmployeeTransferFormat::CSV => ['csv'],
                EmployeeTransferFormat::JSON => ['json'],
                EmployeeTransferFormat::XML => ['xml'],
                EmployeeTransferFormat::XLSX => ['xlsx'],
                default => [],
            };

            if (! in_array($extension, $allowedExtensions, true)) {
                $validator->errors()->add('file', __('employees.import.errors.extension_mismatch'));
            }
        });
    }

    public function requestedFormat(): string
    {
        $format = $this->input('format', $this->route('format'));

        return EmployeeTransferFormat::normalize((string) $format);
    }

    public function uploadedFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }
}
