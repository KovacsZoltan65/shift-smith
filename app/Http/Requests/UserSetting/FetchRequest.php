<?php

declare(strict_types=1);

namespace App\Http\Requests\UserSetting;

use App\Http\Requests\UserSetting\Concerns\ResolvesUserSettingScope;
use App\Models\UserSetting;
use App\Policies\UserSettingPolicy;
use Illuminate\Foundation\Http\FormRequest;

class FetchRequest extends FormRequest
{
    use ResolvesUserSettingScope;

    public function authorize(): bool
    {
        return $this->user()?->can(UserSettingPolicy::PERM_VIEW_ANY, UserSetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:int,bool,string,json'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sortBy' => ['nullable', 'string', 'in:key,group,type,updated_at,created_at'],
            'sortDir' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sortField' => ['nullable', 'string'],
            'sortOrder' => ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $sortBy = $this->input('sortBy');
        $sortDir = $this->input('sortDir');

        if (($sortBy === null || $sortBy === '') && $this->filled('sortField')) {
            $sortBy = $this->input('sortField');
        }

        if (($sortDir === null || $sortDir === '') && $this->has('sortOrder')) {
            $sortDir = ((string) $this->input('sortOrder') === '1' || $this->input('sortOrder') === 1) ? 'asc' : 'desc';
        }

        $this->merge([
            'sortBy' => $sortBy === '' ? null : $sortBy,
            'sortDir' => $sortDir === '' ? null : strtolower((string) $sortDir),
        ]);
    }

    public function validatedFilters(): array
    {
        $data = $this->validated();

        return [
            'q' => isset($data['q']) && is_string($data['q']) ? trim($data['q']) : null,
            'group' => isset($data['group']) && is_string($data['group']) ? trim($data['group']) : null,
            'type' => $data['type'] ?? null,
            'sortBy' => $data['sortBy'] ?? 'key',
            'sortDir' => $data['sortDir'] ?? 'asc',
            'page' => (int) ($data['page'] ?? 1),
            'perPage' => (int) ($data['perPage'] ?? 10),
            'user_id' => is_numeric($data['user_id'] ?? null) ? (int) $data['user_id'] : null,
        ];
    }
}
