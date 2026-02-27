<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $id = (int) $this->route('id');
        $target = $id > 0 ? User::query()->find($id) : null;

        return $target instanceof User
            ? ($this->user()?->can(UserPolicy::PERM_UPDATE, $target) ?? false)
            : false;
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
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', "unique:users,email,{$id}"],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
            ],
            'password_confirmation' => ['nullable', 'required_with:password', 'string'],
        ];
    }
}
