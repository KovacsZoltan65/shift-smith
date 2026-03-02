<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(UserPolicy::PERM_CREATE, User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where(function ($query): void {
                    $tenantGroupId = (int) $this->session()->get('current_tenant_group_id', 0);

                    if ($tenantGroupId > 0) {
                        $query
                            ->where('tenant_group_id', $tenantGroupId)
                            ->where('active', true);
                    }
                }),
            ],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
            ],
            'password_confirmation' => ['required_with:password', 'string'],
        ];
    }
}
