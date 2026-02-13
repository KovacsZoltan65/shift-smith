<?php

namespace App\Http\Requests\WorkShift;

use App\Models\WorkShift;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('work_shift.deleteAny', WorkShift::class);
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