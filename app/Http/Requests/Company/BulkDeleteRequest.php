<?php

namespace App\Http\Requests\Company;

use App\Models\Company;
use App\Policies\CompanyPolicy;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(CompanyPolicy::PERM_DELETE_ANY, Company::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', "min:1",],
            'ids.*' => ['integer', 'distinct', 'exists:companies,id'],
        ];
    }
}
