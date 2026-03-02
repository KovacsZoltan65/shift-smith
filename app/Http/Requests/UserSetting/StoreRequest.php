<?php

declare(strict_types=1);

namespace App\Http\Requests\UserSetting;

use App\Http\Requests\AppSetting\StoreRequest as BaseStoreRequest;
use App\Http\Requests\UserSetting\Concerns\ResolvesUserSettingScope;
use App\Models\UserSetting;
use App\Policies\UserSettingPolicy;
use Illuminate\Validation\Rule;

class StoreRequest extends BaseStoreRequest
{
    use ResolvesUserSettingScope;

    public function authorize(): bool
    {
        return $this->user()?->can(UserSettingPolicy::PERM_CREATE, UserSetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'key' => [
                'required',
                'string',
                'max:190',
                Rule::unique('user_settings', 'key')->where(fn ($query) => $query
                    ->where('company_id', $this->currentCompanyId())
                    ->where('user_id', $this->targetUserId())),
            ],
            'type' => ['required', 'string', 'in:int,bool,string,json'],
            'group' => ['required', 'string', 'max:100'],
            'label' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'value' => ['nullable'],
        ];
    }
}
