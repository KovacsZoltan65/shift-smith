<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\TenantGroup;
use App\Support\CurrentCompanyContext;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    TenantGroup::forgetCurrent();
});

afterEach(function (): void {
    TenantGroup::forgetCurrent();
});

function contextRequest(array $sessionData = []): Request
{
    $request = Request::create('/_test/current-company-context', 'GET');
    $session = new Store('testing', new ArraySessionHandler(120));
    $session->start();

    foreach ($sessionData as $key => $value) {
        $session->put($key, $value);
    }

    $request->setLaravelSession($session);

    return $request;
}

it('aborts when no current company exists in session', function (): void {
    $request = contextRequest();
    $context = app(CurrentCompanyContext::class);

    try {
        $context->resolve($request);
        $this->fail('Expected HttpException was not thrown.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);
        expect($exception->getMessage())->toContain('Nincs kiválasztott cég kontextus');
    }
});

it('clears session and aborts when session company tenant mismatches', function (): void {
    Log::spy();

    $tenantOne = TenantGroup::factory()->create();
    $tenantTwo = TenantGroup::factory()->create();
    $tenantOne->makeCurrent();

    $company = Company::factory()->create([
        'tenant_group_id' => $tenantTwo->id,
        'active' => true,
    ]);

    $request = contextRequest([
        'current_company_id' => $company->id,
        'current_tenant_group_id' => $tenantOne->id,
    ]);

    $context = app(CurrentCompanyContext::class);
    try {
        $context->resolve($request);
        $this->fail('Expected HttpException was not thrown.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
        expect($exception->getMessage())->toContain('tenant kontextussal');
    }

    expect($request->session()->has('current_company_id'))->toBeFalse();
    expect($request->session()->has('current_tenant_group_id'))->toBeFalse();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'company_context.drift_reset'
            && ($context['reason'] ?? null) === 'invalid_company_for_tenant');
});

it('returns current company id when session company matches tenant context', function (): void {
    $tenant = TenantGroup::factory()->create();
    $tenant->makeCurrent();

    $company = Company::factory()->create([
        'tenant_group_id' => $tenant->id,
        'active' => true,
    ]);

    $request = contextRequest([
        'current_company_id' => $company->id,
        'current_tenant_group_id' => $tenant->id,
    ]);

    $context = app(CurrentCompanyContext::class);
    $companyId = $context->resolve($request);

    expect($companyId)->toBe($company->id);
    expect($request->session()->get('current_company_id'))->toBe($company->id);
    expect($request->session()->get('current_tenant_group_id'))->toBe($tenant->id);
});
