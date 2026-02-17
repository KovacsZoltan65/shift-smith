<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class UserPolicy extends BasePolicy
{
    use HandlesAuthorization;

    public const PERM_VIEW_ANY = 'users.viewAny';
    public const PERM_VIEW = 'users.view';
    public const PERM_CREATE = 'users.create';
    public const PERM_UPDATE = 'users.update';
    public const PERM_UPDATE_ANY = 'users.updateAny';
    public const PERM_UPDATE_SELF = 'users.updateSelf';
    public const PERM_DELETE = 'users.delete';
    public const PERM_DELETE_ANY = 'users.deleteAny';

    protected static function entity(): string { return 'users'; }

    public function viewAny(User $user): bool
    {
        return $user->can(self::PERM_VIEW_ANY);
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id
            ? $user->can(self::PERM_VIEW)
            : $user->can(self::PERM_VIEW_ANY);
    }

    public function create(User $user): bool
    {
        return $user->can(self::PERM_CREATE);
    }

    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return $user->can(self::PERM_UPDATE_SELF) || $user->can(self::PERM_UPDATE_ANY);
        }

        return $user->can(self::PERM_UPDATE_ANY);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->id !== $model->id && $user->can(self::PERM_DELETE);
    }
}

