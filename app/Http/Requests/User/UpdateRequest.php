<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');
        
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => "required|unique:users,email,{$id}",
            //'password'              => ['nullable', 'confirmed', Password::defaults()],
            //'password_confirmation' => 'sometimes|required_with:password|same:password',
            //'role'                  => ['required'],
        ];
    }
}
