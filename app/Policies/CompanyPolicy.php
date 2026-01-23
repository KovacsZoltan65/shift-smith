<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        // superadmin mindent
        return $user->hasRole('superadmin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        // példa: admin és manager láthatja a listát
        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function view(User $user, Company $model): bool
    {
        // önmagát láthatja, admin/manager már a before nélkül is igaz lehetne,
        // de a before miatt superadmin úgyis true
        return $user->id === $model->id || $user->hasAnyRole(['admin', 'manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Company $model): bool
    {
        // admin bárkit, user csak magát
        return $user->hasRole('admin') || $user->id === $model->id;
    }

    public function delete(User $user, Company $model): bool
    {
        // admin törölhet, de magát ne (józan ész)
        return $user->hasRole('admin') && $user->id !== $model->id;
    }
}
