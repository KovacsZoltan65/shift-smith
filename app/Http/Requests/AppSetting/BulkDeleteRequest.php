<?php

declare(strict_types=1);

namespace App\Http\Requests\AppSetting;

use App\Models\AppSetting;
use App\Policies\AppSettingPolicy;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(AppSettingPolicy::PERM_DELETE_ANY, AppSetting::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:app_settings,id'],
        ];
    }
}
