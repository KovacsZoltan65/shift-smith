<?php

namespace App\Http\Requests\WorkShift;

use App\Models\WorkShift;
use App\Policies\WorkShiftPolicy;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(WorkShiftPolicy::PERM_CREATE, WorkShift::class) ?? false;
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