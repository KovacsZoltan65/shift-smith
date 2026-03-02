<?php

declare(strict_types=1);

namespace App\Http\Requests\UserSetting;

use App\Http\Requests\UserSetting\Concerns\ResolvesUserSettingScope;
use App\Models\UserSetting;
use App\Policies\UserSettingPolicy;
use Illuminate\Validation\Rule;

class UpdateRequest extends StoreRequest
{
    use ResolvesUserSettingScope;

    public function authorize(): bool
    {
        return $this->user()?->can(UserSettingPolicy::PERM_UPDATE, UserSetting::class) ?? false;
    }

    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'key' => [
                'required',
                'string',
                'max:190',
                Rule::unique('user_settings', 'key')
                    ->ignore($id, 'id')
                    ->where(fn ($query) => $query
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
