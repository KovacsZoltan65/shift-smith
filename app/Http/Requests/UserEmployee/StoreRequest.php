<?php

declare(strict_types=1);

namespace App\Http\Requests\UserEmployee;

use App\Models\Employee;
use App\Models\TenantGroup;
use App\Models\User;
use App\Services\Access\CompanyAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('user_employees.create') ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tenantGroupId = TenantGroup::current()?->id;
            $actor = $this->user();
            $employee = Employee::query()->find((int) $this->input('employee_id'));

            if (! is_numeric($tenantGroupId) || (int) $tenantGroupId <= 0) {
                $validator->errors()->add('employee_id', 'Hiányzó tenant kontextus.');
                return;
            }

            if (! $actor instanceof User) {
                $validator->errors()->add('employee_id', 'Érvénytelen felhasználó kontextus.');
                return;
            }

            if (! $employee instanceof Employee) {
                $validator->errors()->add('employee_id', 'A dolgozó nem található.');
                return;
            }

            if ((bool) ($employee->active ?? true) === false) {
                $validator->errors()->add('employee_id', 'Inaktív dolgozó nem rendelhető hozzá.');
                return;
            }

            $tenantId = (int) $tenantGroupId;
            $currentCompanyId = (int) $this->session()->get('current_company_id', 0);

            $tenantCompanyQuery = $employee->companies()
                ->where('companies.tenant_group_id', $tenantId)
                ->where('companies.active', true)
                ->where('company_employee.active', true);

            if ($currentCompanyId > 0) {
                $tenantCompanyQuery->where('companies.id', $currentCompanyId);
            }

            if (! $tenantCompanyQuery->exists()) {
                $validator->errors()->add('employee_id', 'A dolgozó nem a jelenlegi tenant/cég scope-ban van.');
                return;
            }

            if ($actor->hasRole('superadmin')) {
                return;
            }

            /** @var CompanyAccessService $accessService */
            $accessService = app(CompanyAccessService::class);
            $actorCompanyIds = $accessService->accessibleCompanyIds($actor);

            if ($currentCompanyId > 0 && ! in_array($currentCompanyId, $actorCompanyIds, true)) {
                $validator->errors()->add('employee_id', 'Nincs jogosultságod a kiválasztott céghez.');
                return;
            }

            $allowedCompanyIds = $currentCompanyId > 0
                ? [$currentCompanyId]
                : $actorCompanyIds;

            if ($allowedCompanyIds === []) {
                $validator->errors()->add('employee_id', 'Nincs elérhető közös cég scope.');
                return;
            }

            $hasSharedScope = $employee->companies()
                ->where('companies.tenant_group_id', $tenantId)
                ->where('companies.active', true)
                ->where('company_employee.active', true)
                ->whereIn('companies.id', $allowedCompanyIds)
                ->exists();

            if (! $hasSharedScope) {
                $validator->errors()->add('employee_id', 'A dolgozó nem rendelhető a jelenlegi scope alapján.');
            }
        });
    }
}
