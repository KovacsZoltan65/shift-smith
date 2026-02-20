<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

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
        \App\Models\Employee::class => \App\Policies\EmployeePolicy::class,
        \App\Models\Admin\Role::class => \App\Policies\RolePolicy::class,
        \App\Models\Admin\Permission::class => \App\Policies\PermissionPolicy::class,
        \App\Models\WorkShift::class => \App\Policies\WorkShiftPolicy::class,
        \App\Models\WorkShiftAssignment::class => \App\Policies\WorkShiftAssigmentPolicy::class,
        \App\Models\WorkSchedule::class => \App\Policies\WorkSchedulePolicy::class,
        \App\Models\WorkScheduleAssignment::class => \App\Policies\WorkScheduleAssignmentPolicy::class,
        \App\Models\WorkPattern::class => \App\Policies\WorkPatternPolicy::class,
        \App\Models\EmployeeWorkPattern::class => \App\Policies\EmployeeWorkPatternPolicy::class,
        
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
        //
    }
}
