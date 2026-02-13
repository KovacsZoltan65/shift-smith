<?php

namespace App\Http\Requests\WorkShift;

use App\Models\WorkShift;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('work_shift.delete', WorkShift::class);
    }
    
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id'   => ['required', 'int',],
        ];
    }
}

