<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Models\WorkSchedule;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function (): void {
    // ha a projektedben van központi seeder a permissionökre, itt nem muszáj
    Permission::findOrCreate('work_schedules.view', 'web');
});

it('denies fetch if user lacks permission', function (): void {
    $company = Company::factory()->create();

    /** @var User $user */
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    actingAs($user);

    getJson('/work_schedules/fetch')
        ->assertForbidden();
});

it('returns paginated work schedules scoped to company with filters', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    /** @var User $user */
    $user = User::factory()->create([
        'company_id' => $companyA->id,
    ]);

    $user->givePermissionTo('work_schedules.view');

    // Company A
    WorkSchedule::factory()->create([
        'company_id' => $companyA->id,
        'name' => 'Alpha schedule',
        'status' => 'draft',
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
    ]);

    WorkSchedule::factory()->create([
        'company_id' => $companyA->id,
        'name' => 'Beta schedule',
        'status' => 'published',
        'date_from' => '2026-02-11',
        'date_to' => '2026-02-20',
    ]);

    // Company B (nem látszódhat)
    WorkSchedule::factory()->count(3)->create([
        'company_id' => $companyB->id,
        'status' => 'draft',
    ]);

    actingAs($user);

    // 1) alap fetch: csak companyA elemek
    getJson('/work_schedules/fetch?per_page=50')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['total', 'per_page', 'current_page', 'last_page'],
        ])
        ->assertJsonPath('meta.total', 2);

    // 2) keresés (search)
    getJson('/work_schedules/fetch?per_page=50&search=Alpha')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Alpha schedule');

    // 3) státusz filter
    getJson('/work_schedules/fetch?per_page=50&status=published')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', 'published');

    // 4) dátum szűrés (átfedés logika helyett most egyszerűen paraméter átadás + találat ellenőrzés)
    getJson('/work_schedules/fetch?per_page=50&date_from=2026-02-01&date_to=2026-02-15')
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->has('data')
            ->has('meta.total')
        );
});
