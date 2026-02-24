<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Policies\AppSettingPolicy;
use Illuminate\Foundation\Http\FormRequest;

class SaveRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $level = (string) $this->input('level', 'app');

        if ($level === 'company' && !$this->filled('company_id')) {
            $sessionCompanyId = $this->session()->get('current_company_id');
            if (is_numeric($sessionCompanyId)) {
                $this->merge(['company_id' => (int) $sessionCompanyId]);
            }
        }
    }

    public function authorize(): bool
    {
        $level = (string) $this->input('level', 'app');

        return match ($level) {
            'app' => $this->user()?->can(AppSettingPolicy::PERM_UPDATE_APP) ?? false,
            'company' => $this->user()?->can(AppSettingPolicy::PERM_UPDATE_COMPANY) ?? false,
            'user' => $this->user()?->can(AppSettingPolicy::PERM_UPDATE_USER) ?? false,
            default => false,
        };
    }

    /**
     * @return array<string, array<int,mixed>>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', 'in:app,company,user'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'values' => ['required', 'array', 'min:1'],
            'values.*.key' => ['required', 'string', 'max:191'],
            'values.*.value' => ['nullable'],
        ];
    }
}
