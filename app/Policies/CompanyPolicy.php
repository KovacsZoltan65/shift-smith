<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;

    public function before(Company $company, string $ability): ?bool
    {
        // superadmin mindent
        return $user->hasRole('superadmin') ? true : null;
    }

    public function viewAny(Company $company): bool
    {
        // példa: admin és manager láthatja a listát
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function view(Company $company, Company $model): bool
    {
        // önmagát láthatja, admin/manager már a before nélkül is igaz lehetne,
        // de a before miatt superadmin úgyis true
        return $company->id === $model->id || $company->hasAnyRole(['admin', 'manager']);
    }

    public function create(Company $company): bool
    {
        return $company->hasRole('admin');
    }

    public function update(Company $company, Company $model): bool
    {
        // admin bárkit, user csak magát
        return $company->hasRole('admin') || $company->id === $model->id;
    }

    public function delete(Company $company, Company $model): bool
    {
        // admin törölhet, de magát ne (józan ész)
        return $company->hasRole('admin') && $company->id !== $model->id;
    }
}
