<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use App\Policies\EmployeePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(EmployeePolicy::PERM_CREATE, Employee::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'company_id'  => ['required', 'integer', 'exists:companies,id'],

            'first_name'  => ['required', 'string', 'max:80'],
            'last_name'   => ['required', 'string', 'max:80'],

            'email'       => ['required', 'email', 'max:120'],
            'address'     => ['nullable', 'string', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:50'],
            'position_id' => [
                'nullable',
                'integer',
                Rule::exists('positions', 'id')->where(
                    fn ($q) => $q->where('company_id', (int) $this->input('company_id'))->whereNull('deleted_at')
                ),
            ],
            'birth_date'  => ['required', 'date', 'before:today'],
            'hired_at'    => ['nullable', 'date'],
            'active'      => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{
     *   company_id:int,
     *   first_name:string,
     *   last_name:string,
     *   email:string,
     *   birth_date:string,
     *   address:string|null,
     *   phone:string|null,
     *   position_id:int|null,
     *   hired_at:string|null,
     *   active:bool
     * }
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        return [
            'company_id' => (int) $data['company_id'],
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'email' => mb_strtolower(trim((string) $data['email']), 'UTF-8'),
            'birth_date' => (string) $data['birth_date'],
            'address' => array_key_exists('address', $data) && $data['address'] !== null
                ? (string) $data['address']
                : null,
            'phone' => array_key_exists('phone', $data) && $data['phone'] !== null
                ? (string) $data['phone']
                : null,
            'position_id' => array_key_exists('position_id', $data) && $data['position_id'] !== null
                ? (int) $data['position_id']
                : null,
            'hired_at' => array_key_exists('hired_at', $data) && $data['hired_at'] !== null
                ? (string) $data['hired_at']
                : null,
            'active' => (bool) ($data['active'] ?? true),
        ];
    }
}
