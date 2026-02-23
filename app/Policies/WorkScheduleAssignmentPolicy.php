<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkShiftAssignment;
use Carbon\CarbonImmutable;

final class WorkScheduleAssignmentPolicy extends BasePolicy
{
    public const PERM_VIEW_ANY = 'work_schedule_assignments.viewAny';
    public const PERM_VIEW = 'work_schedule_assignments.view';
    public const PERM_CREATE = 'work_schedule_assignments.create';
    public const PERM_UPDATE = 'work_schedule_assignments.update';
    public const PERM_DELETE = 'work_schedule_assignments.delete';
    public const PERM_DELETE_ANY = 'work_schedule_assignments.deleteAny';

    protected static function entity(): string
    {
        return 'work_schedule_assignments';
    }

    public function before(User $user, string $ability): ?bool
    {
        if (\in_array($ability, ['update', 'delete'], true)) {
            return null;
        }

        return parent::before($user, $ability);
    }

    public function viewAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_VIEW_ANY));
    }

    public function view(User $user, WorkShiftAssignment $assignment): bool
    {
        return $user->can(self::perm(self::PERM_VIEW));
    }

    public function create(User $user): bool
    {
        return $user->can(self::perm(self::PERM_CREATE));
    }

    public function update(User $user, WorkShiftAssignment $assignment): bool
    {
        return $user->can(self::perm(self::PERM_UPDATE))
            && $this->isDateEditable((string) $assignment->date->format('Y-m-d'));
    }

    public function delete(User $user, WorkShiftAssignment $assignment): bool
    {
        return $user->can(self::perm(self::PERM_DELETE))
            && $this->isDateEditable((string) $assignment->date->format('Y-m-d'));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(self::perm(self::PERM_DELETE_ANY));
    }

    private function isDateEditable(string $date): bool
    {
        return CarbonImmutable::parse($date)->startOfDay()
            ->greaterThanOrEqualTo(CarbonImmutable::today());
    }
}
