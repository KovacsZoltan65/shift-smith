<?php

declare(strict_types=1);

namespace App\Http\Requests\AppSetting;

use App\Models\AppSetting;
use App\Policies\AppSettingPolicy;
use Illuminate\Validation\Rule;

class UpdateRequest extends StoreRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(AppSettingPolicy::PERM_UPDATE, AppSetting::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'key' => ['required', 'string', 'max:190', Rule::unique('app_settings', 'key')->ignore($id, 'id')],
            'type' => ['required', 'string', 'in:int,bool,string,json'],
            'group' => ['required', 'string', 'max:100'],
            'label' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'value' => ['nullable'],
        ];
    }
}
