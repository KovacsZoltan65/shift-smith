<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Policies\UserAssignmentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [

        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\Company::class => \App\Policies\CompanyPolicy::class,
        \App\Models\Position::class => \App\Policies\PositionPolicy::class,
        \App\Models\Employee::class => \App\Policies\EmployeePolicy::class,
        \App\Models\Admin\Role::class => \App\Policies\RolePolicy::class,
        \App\Models\Admin\Permission::class => \App\Policies\PermissionPolicy::class,
        \App\Models\WorkShift::class => \App\Policies\WorkShiftPolicy::class,
        \App\Models\WorkShiftAssignment::class => \App\Policies\WorkScheduleAssignmentPolicy::class,
        \App\Models\WorkPattern::class => \App\Policies\WorkPatternPolicy::class,
        \App\Models\EmployeeWorkPattern::class => \App\Policies\EmployeeWorkPatternPolicy::class,
        \App\Models\AppSetting::class => \App\Policies\AppSettingPolicy::class,
        \App\Models\CompanySetting::class => \App\Policies\CompanySettingPolicy::class,
        \App\Models\EmployeeAbsence::class => \App\Policies\EmployeeAbsencePolicy::class,
        \App\Models\LeaveCategory::class => \App\Policies\LeaveCategoryPolicy::class,
        \App\Models\LeaveType::class => \App\Policies\LeaveTypePolicy::class,
        \App\Models\SickLeaveCategory::class => \App\Policies\SickLeaveCategoryPolicy::class,
        \App\Models\UserSetting::class => \App\Policies\UserSettingPolicy::class,
        \App\Models\UserEmployee::class => \App\Policies\UserEmployeePolicy::class,
        
        //\App\Models\Activity::class               => \App\Policies\ActivityPolicy::class,
        //
        //\App\Models\WorkScheduleAssignment::class => \App\Policies\WorkScheduleAssignmentPolicy::class,
        //\App\Models\Permission::class             => \App\Policies\PermissionPolicy::class,
        //\App\Models\Product::class                => \App\Policies\ProductPolicy::class,
        //\App\Models\Admin\Role::class                   => \App\Policies\RolePolicy::class,
        //\App\Models\WorkSchedule::class           => \App\Policies\WorkSchedulePolicy::class,

        // HA Spatie-t használsz:
        // \Spatie\Permission\Models\Role::class => \App\Policies\RolePolicy::class,
        // \Spatie\Permission\Models\Permission::class => \App\Policies\PermissionPolicy::class,

        // Későbbiek:
        // \App\Models\MetaSetting::class => \App\Policies\Settings\MetaSettingsPolicy::class,
        // \App\Models\AppSetting::class  => \App\Policies\Settings\AppSettingsPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define(UserAssignmentPolicy::PERM_VIEW_ANY, function (\App\Models\User $user): bool {
            $policy = app(UserAssignmentPolicy::class);

            return $policy->before($user, UserAssignmentPolicy::PERM_VIEW_ANY)
                ?? $policy->viewAny($user);
        });

        Gate::define(UserAssignmentPolicy::PERM_UPDATE, function (\App\Models\User $user): bool {
            $policy = app(UserAssignmentPolicy::class);

            return $policy->before($user, UserAssignmentPolicy::PERM_UPDATE)
                ?? $policy->update($user);
        });
    }
}
