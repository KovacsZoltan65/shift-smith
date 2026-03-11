<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Http\Middleware\EnsureCompanySelected;
use App\Http\Middleware\EnsureTenantContext;
use App\Services\Cache\CacheVersionService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\PermissionRegistrar;

dataset('employee_transfer_formats', ['csv', 'json', 'xml', 'xlsx']);

beforeEach(function (): void {
    $this->seedRolesAndPermissions();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('exports only current company rows for each supported format', function (string $format): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [, $companyWithinTenantA] = $this->createTenantWithCompany([], ['tenant_group_id' => $tenantA->id]);
    [, $companyB] = $this->createTenantWithCompany();

    $user = $this->createAdminUser($companyA);

    $positionA = Position::factory()->create(['company_id' => $companyA->id, 'name' => 'Assembler']);
    Position::factory()->create(['company_id' => $companyWithinTenantA->id, 'name' => 'Hidden A']);
    Position::factory()->create(['company_id' => $companyB->id, 'name' => 'Hidden B']);

    Employee::factory()->create([
        'company_id' => $companyA->id,
        'first_name' => 'Alice',
        'last_name' => 'Tenant',
        'email' => 'alice@example.test',
        'position_id' => $positionA->id,
        'birth_date' => '1990-01-01',
        'hired_at' => '2025-01-01',
        'active' => true,
    ]);

    Employee::factory()->create([
        'company_id' => $companyWithinTenantA->id,
        'first_name' => 'Brenda',
        'last_name' => 'Sibling',
        'email' => 'brenda@example.test',
        'birth_date' => '1991-01-01',
    ]);

    Employee::factory()->create([
        'company_id' => $companyB->id,
        'first_name' => 'Cecil',
        'last_name' => 'Foreign',
        'email' => 'cecil@example.test',
        'birth_date' => '1992-01-01',
    ]);

    $response = $this->actingAsUserInCompany($user, $companyA)
        ->get(route('employees.export', ['format' => $format]));

    $response->assertOk();

    $rows = parseEmployeeTransferContent($format, $response->streamedContent());

    $emails = array_column($rows, 'email');

    expect($emails)->toContain('alice@example.test')
        ->and($emails)->not->toContain('brenda@example.test')
        ->and($emails)->not->toContain('cecil@example.test');
})->with('employee_transfer_formats');

it('downloads a single sample template row for each supported format', function (string $format): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);

    Position::factory()->create([
        'company_id' => $company->id,
        'name' => 'Sample Position',
        'active' => true,
    ]);

    $tenant->makeCurrent();

    $response = $this->actingAsUserInCompany($user, $company)
        ->get(route('employees.template', ['format' => $format]));

    $response->assertOk();

    $rows = parseEmployeeTransferContent($format, $response->streamedContent());

    $companyPositionNames = Position::query()
        ->where('company_id', $company->id)
        ->pluck('name')
        ->all();

    expect($rows)->toHaveCount(1)
        ->and($companyPositionNames)->toContain($rows[0]['position_name'])
        ->and($rows[0]['email'])->toBe('jane.doe@example.test');
})->with('employee_transfer_formats');

it('imports a new employee into the current company and bumps cache versions for each supported format', function (string $format): void {
    [$tenantA, $companyA] = $this->createTenantWithCompany();
    [$tenantB, $companyB] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($companyA);

    $positionA = Position::factory()->create([
        'company_id' => $companyA->id,
        'name' => 'Operator',
        'active' => true,
    ]);

    Position::factory()->create([
        'company_id' => $companyB->id,
        'name' => 'Operator',
        'active' => true,
    ]);

    $tenantA->makeCurrent();

    $versioner = app(CacheVersionService::class);
    $employeesFetchBefore = $versioner->get('employees.fetch');
    $employeesSelectorBefore = $versioner->get('selectors.employees');

    $upload = makeEmployeeTransferUpload($format, [[
        'last_name' => 'Imported',
        'first_name' => 'Emma',
        'email' => 'emma.import@example.test',
        'phone' => '+36 30 999 0000',
        'address' => '1024 Budapest, Test utca 5.',
        'position_name' => $positionA->name,
        'birth_date' => '1994-03-10',
        'hired_at' => '2026-03-01',
        'active' => 'igen',
    ]]);

    $response = $this->actingAsUserInCompany($user, $companyA)
        ->post(route('employees.import', ['format' => $format]), [
            'file' => $upload,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.total_rows', 1)
        ->assertJsonPath('data.imported_count', 1)
        ->assertJsonPath('data.failed_count', 0);

    $this->assertDatabaseHas('employees', [
        'company_id' => $companyA->id,
        'email' => 'emma.import@example.test',
        'first_name' => 'Emma',
        'last_name' => 'Imported',
        'position_id' => $positionA->id,
    ]);

    $this->assertDatabaseMissing('employees', [
        'company_id' => $companyB->id,
        'email' => 'emma.import@example.test',
    ]);

    expect($versioner->get('employees.fetch'))->toBeGreaterThan($employeesFetchBefore)
        ->and($versioner->get('selectors.employees'))->toBeGreaterThan($employeesSelectorBefore);
})->with('employee_transfer_formats');

it('fails duplicate and restore-available imports and keeps company scoped position resolution', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    [, $otherCompanyInTenant] = $this->createTenantWithCompany([], ['tenant_group_id' => $tenant->id]);
    $user = $this->createAdminUser($company);

    Position::factory()->create([
        'company_id' => $otherCompanyInTenant->id,
        'name' => 'External Position',
        'active' => true,
    ]);

    Employee::factory()->create([
        'company_id' => $company->id,
        'email' => 'duplicate@example.test',
        'birth_date' => '1990-01-01',
    ]);

    $trashed = Employee::factory()->create([
        'company_id' => $company->id,
        'email' => 'restore@example.test',
        'birth_date' => '1991-01-01',
    ]);
    $trashed->delete();

    $upload = makeEmployeeTransferUpload('csv', [
        [
            'last_name' => 'Dup',
            'first_name' => 'Active',
            'email' => 'duplicate@example.test',
            'phone' => null,
            'address' => null,
            'position_name' => '',
            'birth_date' => '1990-01-01',
            'hired_at' => '2026-03-01',
            'active' => 'true',
        ],
        [
            'last_name' => 'Restore',
            'first_name' => 'SoftDeleted',
            'email' => 'restore@example.test',
            'phone' => null,
            'address' => null,
            'position_name' => '',
            'birth_date' => '1990-01-01',
            'hired_at' => '2026-03-01',
            'active' => 'true',
        ],
        [
            'last_name' => 'Pos',
            'first_name' => 'Scope',
            'email' => 'position.scope@example.test',
            'phone' => null,
            'address' => null,
            'position_name' => 'External Position',
            'birth_date' => '1990-01-01',
            'hired_at' => '2026-03-01',
            'active' => 'true',
        ],
    ]);

    $response = $this->actingAsUserInCompany($user, $company)
        ->post(route('employees.import', ['format' => 'csv']), [
            'file' => $upload,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.total_rows', 3)
        ->assertJsonPath('data.failed_count', 3)
        ->assertJsonPath('data.imported_count', 0);

    expect($response->json('data.rows.0.status'))->toBe('failed')
        ->and($response->json('data.rows.0.message'))->toBe(__('employees.import.rows.duplicate_email'))
        ->and($response->json('data.rows.1.errors.0'))->toBe('restore_available')
        ->and($response->json('data.rows.2.message'))->toBe(__('employees.import.rows.position_not_found'));
});

it('requires employee permissions for export and import', function (): void {
    [$tenant, $company] = $this->createTenantWithCompany();
    $user = $this->createAdminUser($company);
    $user->removeRole('admin');
    $user->forceFill(['email_verified_at' => now()])->save();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();
    $tenant->makeCurrent();

    $this->withoutMiddleware([
        EnsureCompanySelected::class,
        EnsureTenantContext::class,
        \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
    ]);

    $this->actingAsUserInCompany($user, $company)
        ->get(route('employees.export', ['format' => 'csv']))
        ->assertForbidden();

    $upload = makeEmployeeTransferUpload('json', [[
        'last_name' => 'User',
        'first_name' => 'Limited',
        'email' => 'limited@example.test',
        'phone' => null,
        'address' => null,
        'position_name' => '',
        'birth_date' => '1990-01-01',
        'hired_at' => '2026-03-01',
        'active' => 'true',
    ]]);

    $this->actingAsUserInCompany($user, $company)
        ->post(route('employees.import', ['format' => 'json']), [
            'file' => $upload,
        ])
        ->assertForbidden();
});

it('redirects unauthenticated employee transfer requests', function (): void {
    $this->get(route('employees.export', ['format' => 'csv']))
        ->assertRedirect();
});

function parseEmployeeTransferContent(string $format, string $content): array
{
    return match ($format) {
        'csv' => parseCsvTransfer($content),
        'json' => json_decode($content, true, 512, JSON_THROW_ON_ERROR),
        'xml' => parseXmlTransfer($content),
        'xlsx' => parseXlsxTransfer($content),
        default => [],
    };
}

function parseCsvTransfer(string $content): array
{
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $content);
    rewind($stream);

    $header = fgetcsv($stream) ?: [];
    $rows = [];

    while (($row = fgetcsv($stream)) !== false) {
        if ($row === [null] || $row === []) {
            continue;
        }

        /** @var array<string, string|null> $mapped */
        $mapped = array_combine($header, $row);
        $rows[] = $mapped;
    }

    fclose($stream);

    return $rows;
}

function parseXmlTransfer(string $content): array
{
    $xml = simplexml_load_string($content);

    if (! $xml instanceof SimpleXMLElement) {
        return [];
    }

    $rows = [];

    foreach ($xml->employee ?? [] as $employeeNode) {
        $row = [];

        foreach ($employeeNode as $field => $value) {
            $row[$field] = (string) $value;
        }

        $rows[] = $row;
    }

    return $rows;
}

function parseXlsxTransfer(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'employees-xlsx-');
    file_put_contents($path, $content);

    $spreadsheet = IOFactory::load($path);
    $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    $spreadsheet->disconnectWorksheets();
    @unlink($path);

    $header = array_map(static fn ($value): string => (string) $value, array_values($rows[0] ?? []));
    $dataRows = [];

    foreach (array_slice($rows, 1) as $row) {
        /** @var array<string, string|null> $mapped */
        $mapped = array_combine($header, array_map(static fn ($value): ?string => $value === null ? null : (string) $value, $row));
        $dataRows[] = $mapped;
    }

    return $dataRows;
}

function makeEmployeeTransferUpload(string $format, array $rows)
{
    return match ($format) {
        'csv' => Illuminate\Http\UploadedFile::fake()->createWithContent('employees.csv', buildCsvTransfer($rows)),
        'json' => Illuminate\Http\UploadedFile::fake()->createWithContent('employees.json', json_encode($rows, JSON_THROW_ON_ERROR)),
        'xml' => Illuminate\Http\UploadedFile::fake()->createWithContent('employees.xml', buildXmlTransfer($rows)),
        'xlsx' => Illuminate\Http\UploadedFile::fake()->createWithContent('employees.xlsx', buildXlsxTransfer($rows)),
    };
}

function buildCsvTransfer(array $rows): string
{
    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, array_keys($rows[0]));

    foreach ($rows as $row) {
        fputcsv($stream, $row);
    }

    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);

    return $content === false ? '' : $content;
}

function buildXmlTransfer(array $rows): string
{
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><employees />');

    foreach ($rows as $row) {
        $employeeNode = $xml->addChild('employee');

        foreach ($row as $field => $value) {
            $employeeNode->addChild($field, htmlspecialchars((string) ($value ?? '')));
        }
    }

    return (string) $xml->asXML();
}

function buildXlsxTransfer(array $rows): string
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $header = array_keys($rows[0]);

    foreach ($header as $columnIndex => $field) {
        $sheet->setCellValue([$columnIndex + 1, 1], $field);
    }

    foreach (array_values($rows) as $rowIndex => $row) {
        foreach (array_values($row) as $columnIndex => $value) {
            $sheet->setCellValue([$columnIndex + 1, $rowIndex + 2], $value);
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'employees-upload-').'.xlsx';
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($path);
    $spreadsheet->disconnectWorksheets();

    $content = file_get_contents($path) ?: '';
    @unlink($path);

    return $content;
}
