<?php

declare(strict_types=1);

namespace App\Http\Requests\UserSetting;

use App\Http\Requests\UserSetting\Concerns\ResolvesUserSettingScope;
use App\Models\UserSetting;
use App\Policies\UserSettingPolicy;
use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    use ResolvesUserSettingScope;

    public function authorize(): bool
    {
        return $this->user()?->can(UserSettingPolicy::PERM_DELETE_ANY, UserSetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:user_settings,id'],
        ];
    }
}
