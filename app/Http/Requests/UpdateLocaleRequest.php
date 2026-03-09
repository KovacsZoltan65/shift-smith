<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Az alkalmazás nyelvének váltásához érkező kérést validálja.
 */
final class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'locale' => [
                'required',
                'string',
                Rule::in(config('app.supported_locales', ['en', 'hu'])),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locale.required' => __('validation.required'),
            'locale.string' => __('validation.string'),
            'locale.in' => __('validation.in'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'locale' => __('validation.attributes.locale'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $locale = $this->input('locale');

        $this->merge([
            'locale' => \is_string($locale) ? mb_strtolower(trim($locale)) : $locale,
        ]);
    }
}
