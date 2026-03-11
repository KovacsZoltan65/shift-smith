<?php

declare(strict_types=1);

namespace App\Services\EmployeeTransfer;

final class EmployeeTransferFormat
{
    public const CSV = 'csv';
    public const JSON = 'json';
    public const XML = 'xml';
    public const XLSX = 'xlsx';

    /** @var list<string> */
    public const ALL = [
        self::CSV,
        self::JSON,
        self::XML,
        self::XLSX,
    ];

    /** @var list<string> */
    public const FIELDS = [
        'last_name',
        'first_name',
        'email',
        'phone',
        'address',
        'position_name',
        'birth_date',
        'hired_at',
        'active',
    ];

    public static function normalize(string $format): string
    {
        return strtolower(trim($format));
    }
}
