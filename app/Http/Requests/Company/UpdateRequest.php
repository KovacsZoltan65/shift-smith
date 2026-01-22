<?php

namespace App\Http\Requests\Company;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', Company::class);
    }
    
    public function rules(): array
    {
        $id = (int) $this->route('id');
        
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => "required|unique:companies,email,{$id}",
        ];
    }
}