<?php

declare(strict_types=1);

namespace App\Http\Requests\AppSetting;

use App\Models\AppSetting;
use App\Policies\AppSettingPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRequest extends FormRequest
{
    private mixed $normalizedValue = null;

    public function authorize(): bool
    {
        return $this->user()?->can(AppSettingPolicy::PERM_CREATE, AppSetting::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:190', Rule::unique('app_settings', 'key')],
            'type' => ['required', 'string', 'in:int,bool,string,select,json'],
            'group' => ['required', 'string', 'max:100'],
            'label' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'value' => ['nullable'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => is_string($this->input('key')) ? trim($this->input('key')) : $this->input('key'),
            'group' => is_string($this->input('group')) ? trim($this->input('group')) : $this->input('group'),
            'label' => is_string($this->input('label')) ? trim($this->input('label')) : $this->input('label'),
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $result = $this->normalizeValue();

            if ($result['valid'] === false) {
                $validator->errors()->add('value', (string) $result['message']);
                return;
            }

            $this->normalizedValue = $result['value'];
        });
    }

    /**
     * @return array{
     *   key: string,
     *   value: mixed,
     *   type: string,
     *   group: string,
     *   label?: string|null,
     *   description?: string|null
     * }
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        return [
            'key' => (string) $data['key'],
            'value' => $this->normalizedValue,
            'type' => (string) $data['type'],
            'group' => (string) $data['group'],
            'label' => $data['label'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    }

    /**
     * @return array{valid: bool, value?: mixed, message?: string}
     */
    private function normalizeValue(): array
    {
        $type = (string) $this->input('type');
        $value = $this->input('value');

        return match ($type) {
            'int' => $this->normalizeInt($value),
            'bool' => $this->normalizeBool($value),
            'string', 'select' => $this->normalizeStringValue($value),
            'json' => $this->normalizeJson($value),
            default => ['valid' => false, 'message' => __('app_settings.validation.invalid_type')],
        };
    }

    /**
     * @return array{valid: bool, value?: mixed, message?: string}
     */
    private function normalizeInt(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['valid' => true, 'value' => null];
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return ['valid' => false, 'message' => __('app_settings.validation.int_required')];
        }

        return ['valid' => true, 'value' => (int) $value];
    }

    /**
     * @return array{valid: bool, value?: mixed, message?: string}
     */
    private function normalizeBool(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['valid' => true, 'value' => null];
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($normalized === null) {
            return ['valid' => false, 'message' => __('app_settings.validation.bool_required')];
        }

        return ['valid' => true, 'value' => $normalized];
    }

    /**
     * @return array{valid: bool, value?: mixed, message?: string}
     */
    private function normalizeStringValue(mixed $value): array
    {
        if ((string) $this->input('type') === 'select' && (string) $this->input('key') === 'app.locale') {
            $normalized = $value === null ? null : (string) $value;
            $supported = config('app.supported_locales', ['en', 'hu']);

            if ($normalized !== null && ! in_array($normalized, $supported, true)) {
                return ['valid' => false, 'message' => __('app_settings.validation.locale_unsupported')];
            }
        }

        if ($value === null) {
            return ['valid' => true, 'value' => null];
        }

        if (is_array($value) || is_object($value)) {
            return ['valid' => false, 'message' => __('app_settings.validation.string_required')];
        }

        return ['valid' => true, 'value' => (string) $value];
    }

    /**
     * @return array{valid: bool, value?: mixed, message?: string}
     */
    private function normalizeJson(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['valid' => true, 'value' => null];
        }

        if (is_array($value)) {
            return ['valid' => true, 'value' => $value];
        }

        if (is_object($value)) {
            return ['valid' => true, 'value' => (array) $value];
        }

        if (!is_string($value)) {
            return ['valid' => false, 'message' => __('app_settings.validation.json_object_or_array')];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['valid' => false, 'message' => __('app_settings.validation.json_invalid')];
        }

        if (!is_array($decoded)) {
            return ['valid' => false, 'message' => __('app_settings.validation.json_object_or_array')];
        }

        return ['valid' => true, 'value' => $decoded];
    }
}
