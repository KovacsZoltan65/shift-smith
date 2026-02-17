<?php

namespace App\Http\Requests\WorkShift;

use App\Models\WorkShift;
use App\Policies\WorkShiftPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(WorkShiftPolicy::PERM_UPDATE, WorkShift::class);
    }
    
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}