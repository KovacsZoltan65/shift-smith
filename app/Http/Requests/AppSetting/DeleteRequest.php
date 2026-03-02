<?php

declare(strict_types=1);

namespace App\Http\Requests\AppSetting;

use App\Models\AppSetting;
use App\Policies\AppSettingPolicy;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(AppSettingPolicy::PERM_DELETE, AppSetting::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer'],
        ];
    }
}
