<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\TenantGroup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A landlord oldali TenantGroup létrehozási kéréseket validálja.
 */
final class TenantGroupStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TenantGroup::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', 'unique:tenant_groups,code'],
            'status' => ['nullable', 'string', 'max:50'],
            'active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('validation.required'),
            'name.string' => __('validation.string'),
            'name.max' => __('validation.max.string'),
            'code.required' => __('validation.required'),
            'code.string' => __('validation.string'),
            'code.max' => __('validation.max.string'),
            'code.unique' => __('validation.unique'),
            'status.string' => __('validation.string'),
            'status.max' => __('validation.max.string'),
            'active.required' => __('validation.required'),
            'active.boolean' => __('validation.boolean'),
            'notes.string' => __('validation.string'),
            'notes.max' => __('validation.max.string'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('validation.attributes.name'),
            'code' => __('validation.attributes.code'),
            'status' => __('validation.attributes.status'),
            'active' => __('validation.attributes.active'),
            'notes' => __('validation.attributes.notes'),
        ];
    }

    protected function prepareForValidation(): void
    {
        // A string mezőket még validáció előtt tisztítjuk, így az egyediség- és hosszellenőrzés
        // ugyanazokra a kanonikus értékekre fut le, amelyeket később a service is megkap.
        $this->merge([
            'name' => \is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
            'code' => \is_string($this->input('code')) ? trim((string) $this->input('code')) : $this->input('code'),
            'status' => \is_string($this->input('status')) ? trim((string) $this->input('status')) : $this->input('status'),
            'notes' => \is_string($this->input('notes')) ? trim((string) $this->input('notes')) : $this->input('notes'),
            'active' => $this->has('active') ? $this->boolean('active') : $this->input('active'),
        ]);
    }
}
