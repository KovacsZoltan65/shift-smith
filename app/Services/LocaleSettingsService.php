<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;

final class LocaleSettingsService
{
    public const KEY = 'app.locale';

    public function __construct(
        private readonly SettingsRepository $settingsRepository,
    ) {}

    /**
     * @return list<string>
     */
    public function supportedLocales(): array
    {
        $configured = config('app.supported_locales', [config('app.locale', 'en')]);

        $locales = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), is_array($configured) ? $configured : []),
            static fn (string $value): bool => $value !== ''
        )));

        if ($locales === []) {
            $fallback = trim((string) config('app.locale', 'en'));

            return [$fallback !== '' ? $fallback : 'en'];
        }

        return $locales;
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    public function availableLocales(): array
    {
        $configured = config('app.available_locales', []);
        $supported = $this->supportedLocales();
        $options = [];

        foreach (is_array($configured) ? $configured : [] as $entry) {
            if (is_array($entry)) {
                $value = trim((string) ($entry['value'] ?? $entry['locale'] ?? $entry['code'] ?? ''));
                $label = trim((string) ($entry['label'] ?? $entry['name'] ?? ''));
            } else {
                $value = trim((string) $entry);
                $label = $value;
            }

            if ($value === '' || !in_array($value, $supported, true)) {
                continue;
            }

            $options[] = [
                'label' => $label !== '' ? $label : strtoupper($value),
                'value' => $value,
            ];
        }

        if ($options !== []) {
            return array_values($options);
        }

        return array_map(
            static fn (string $locale): array => [
                'label' => strtoupper($locale),
                'value' => $locale,
            ],
            $supported
        );
    }

    public function isSupported(mixed $locale): bool
    {
        if (! is_string($locale)) {
            return false;
        }

        return in_array(trim($locale), $this->supportedLocales(), true);
    }

    public function validationRule(): string
    {
        return 'required|string|in:'.implode(',', $this->supportedLocales());
    }

    public function resolve(?int $companyId = null, ?int $userId = null): string
    {
        $supported = $this->supportedLocales();

        $userValue = $userId !== null
            ? ($this->settingsRepository->userValuesByKeys($userId, [self::KEY])[self::KEY] ?? null)
            : null;
        if ($this->isSupported($userValue)) {
            return (string) $userValue;
        }

        $companyValue = $companyId !== null
            ? $this->settingsRepository->companyValue($companyId, self::KEY)
            : null;
        if ($this->isSupported($companyValue)) {
            return (string) $companyValue;
        }

        $appValue = $this->settingsRepository->appValue(self::KEY);
        if ($this->isSupported($appValue)) {
            return (string) $appValue;
        }

        $fallback = trim((string) config('app.locale', 'en'));

        if ($fallback !== '' && in_array($fallback, $supported, true)) {
            return $fallback;
        }

        return $supported[0] ?? 'en';
    }

    public function currentRequestLocale(): string
    {
        $userId = auth()->id();
        $companyId = app()->bound(CurrentCompany::class) && request()?->hasSession()
            ? app(CurrentCompany::class)->currentCompanyId(request())
            : null;

        return $this->resolve($companyId, $userId !== null ? (int) $userId : null);
    }
}
