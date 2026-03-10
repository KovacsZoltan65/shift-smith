<?php

namespace App\Providers;

use App\Interfaces\AppSettingRepositoryInterface;
use App\Interfaces\Admin\PermissionRepositoryInterface;
use App\Interfaces\Admin\RoleRepositoryInterface;
use App\Interfaces\CompanyRepositoryInterface;
use App\Interfaces\CompanySettingRepositoryInterface;
use App\Repositories\Dashboard\DashboardRepositoryInterface;
use App\Interfaces\EmployeeRepositoryInterface;
use App\Interfaces\EmployeeProfileRepositoryInterface;
use App\Interfaces\LeaveBalanceRepositoryInterface;
use App\Interfaces\MonthClosureRepositoryInterface;
use App\Interfaces\EmployeeWorkPatternRepositoryInterface;
use App\Interfaces\PositionRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\UserSettingRepositoryInterface;
use App\Interfaces\WorkPatternRepositoryInterface;
use App\Interfaces\WorkScheduleRepositoryInterface;
use App\Interfaces\WorkScheduleAssignmentRepositoryInterface;
use App\Interfaces\WorkShiftAssignmentRepositoryInterface;
use App\Interfaces\WorkShiftRepositoryInterface;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\CompanyUser;
use App\Models\Employee;
use App\Models\UserEmployee;
use App\Observers\CompanyObserver;
use App\Observers\CompanyEmployeeObserver;
use App\Observers\CompanyUserObserver;
use App\Observers\EmployeeObserver;
use App\Observers\UserEmployeeObserver;
use App\Repositories\Admin\PermissionRepository;
use App\Repositories\Admin\RoleRepository;
use App\Repositories\AppSettingRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\CompanySettingRepository;
use App\Repositories\Dashboard\DashboardRepository;
use App\Repositories\EmployeeAbsenceRepository;
use App\Repositories\EmployeeAbsenceRepositoryInterface;
use App\Repositories\EmployeeSupervisorRepository;
use App\Repositories\EmployeeSupervisorRepositoryInterface;
use App\Repositories\EmployeeRepository;
use App\Repositories\EmployeeProfileRepository;
use App\Repositories\LeaveBalanceRepository;
use App\Repositories\LeaveCategoryRepository;
use App\Repositories\LeaveCategoryRepositoryInterface;
use App\Repositories\LeaveTypeRepository;
use App\Repositories\LeaveTypeRepositoryInterface;
use App\Repositories\SickLeaveCategoryRepository;
use App\Repositories\SickLeaveCategoryRepositoryInterface;
use App\Repositories\EmployeeWorkPatternRepository;
use App\Repositories\MonthClosureRepository;
use App\Repositories\PositionRepository;
use App\Repositories\PositionOrgLevelRepository;
use App\Repositories\PositionOrgLevelRepositoryInterface;
use App\Repositories\Org\OrgHierarchyRepository;
use App\Repositories\Org\OrgHierarchyRepositoryInterface;
use App\Repositories\Org\OrgHierarchyDesignSettingsRepository;
use App\Repositories\Org\OrgHierarchyDesignSettingsRepositoryInterface;
use App\Repositories\Tenant\TenantGroupRepository;
use App\Repositories\Tenant\TenantGroupRepositoryInterface;
use App\Repositories\UserRepository;
use App\Repositories\UserSettingRepository;
use App\Repositories\UserEmployeeRepository;
use App\Repositories\UserEmployeeRepositoryInterface;
use App\Services\LocaleSettingsService;
use App\Repositories\UserAssignments\UserAssignmentRepository;
use App\Repositories\UserAssignments\UserAssignmentRepositoryInterface;
use App\Repositories\WorkPatternRepository;
use App\Repositories\WorkScheduleRepository;
use App\Repositories\WorkScheduleAssignmentRepository;
use App\Repositories\WorkShiftAssignmentRepository;
use App\Repositories\WorkShiftRepository;
use App\Services\Settings\SettingsManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class, 
            UserRepository::class
        );
        $this->app->bind(
            UserSettingRepositoryInterface::class,
            UserSettingRepository::class
        );
        $this->app->bind(
            CompanyRepositoryInterface::class, 
            CompanyRepository::class
        );
        $this->app->bind(
            AppSettingRepositoryInterface::class,
            AppSettingRepository::class
        );
        $this->app->bind(
            CompanySettingRepositoryInterface::class,
            CompanySettingRepository::class
        );
        $this->app->bind(
            DashboardRepositoryInterface::class,
            DashboardRepository::class
        );
        $this->app->bind(
            TenantGroupRepositoryInterface::class,
            TenantGroupRepository::class
        );
        $this->app->bind(
            RoleRepositoryInterface::class, 
            RoleRepository::class
        );
        $this->app->bind(
            PermissionRepositoryInterface::class, 
            PermissionRepository::class
        );
        $this->app->bind(
            EmployeeRepositoryInterface::class, 
            EmployeeRepository::class
        );
        $this->app->bind(
            EmployeeAbsenceRepositoryInterface::class,
            EmployeeAbsenceRepository::class
        );
        $this->app->bind(
            EmployeeSupervisorRepositoryInterface::class,
            EmployeeSupervisorRepository::class
        );
        $this->app->bind(
            EmployeeProfileRepositoryInterface::class,
            EmployeeProfileRepository::class
        );
        $this->app->bind(
            LeaveBalanceRepositoryInterface::class,
            LeaveBalanceRepository::class
        );
        $this->app->bind(
            MonthClosureRepositoryInterface::class,
            MonthClosureRepository::class
        );
        $this->app->bind(
            LeaveCategoryRepositoryInterface::class,
            LeaveCategoryRepository::class
        );
        $this->app->bind(
            LeaveTypeRepositoryInterface::class,
            LeaveTypeRepository::class
        );
        $this->app->bind(
            SickLeaveCategoryRepositoryInterface::class,
            SickLeaveCategoryRepository::class
        );
        $this->app->bind(
            PositionRepositoryInterface::class,
            PositionRepository::class
        );
        $this->app->bind(
            PositionOrgLevelRepositoryInterface::class,
            PositionOrgLevelRepository::class
        );
        $this->app->bind(
            OrgHierarchyRepositoryInterface::class,
            OrgHierarchyRepository::class
        );
        $this->app->bind(
            OrgHierarchyDesignSettingsRepositoryInterface::class,
            OrgHierarchyDesignSettingsRepository::class
        );
        $this->app->bind(
            WorkShiftRepositoryInterface::class, 
            WorkShiftRepository::class
        );
        $this->app->bind(
            WorkShiftAssignmentRepositoryInterface::class,
            WorkShiftAssignmentRepository::class
        );
        $this->app->bind(
            WorkScheduleAssignmentRepositoryInterface::class,
            WorkScheduleAssignmentRepository::class
        );
        $this->app->bind(
            WorkScheduleRepositoryInterface::class,
            WorkScheduleRepository::class
        );
        $this->app->bind(
            WorkPatternRepositoryInterface::class,
            WorkPatternRepository::class
        );
        $this->app->bind(
            EmployeeWorkPatternRepositoryInterface::class,
            EmployeeWorkPatternRepository::class
        );
        $this->app->bind(
            UserEmployeeRepositoryInterface::class,
            UserEmployeeRepository::class
        );
        $this->app->bind(
            UserAssignmentRepositoryInterface::class,
            UserAssignmentRepository::class
        );
        $this->app->singleton('settings.manager', SettingsManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
//        \DB::listen(function ($query) {
//            \Log::info('SQL', [
//                'sql' => $query->sql,
//                'bindings' => $query->bindings,
//                'time_ms' => $query->time,
//            ]);
//        });

        Employee::observe(EmployeeObserver::class);
        Company::observe(CompanyObserver::class);
        CompanyEmployee::observe(CompanyEmployeeObserver::class);
        CompanyUser::observe(CompanyUserObserver::class);
        UserEmployee::observe(UserEmployeeObserver::class);
        
        if (!\defined('APP_ACTIVE'))   \define('APP_ACTIVE', 1);
        if (!\defined('APP_INACTIVE')) \define('APP_INACTIVE', 0);

        if (!\defined('APP_TRUE'))     \define('APP_TRUE', true);
        if (!\defined('APP_FALSE'))    \define('APP_FALSE', false);

        Inertia::share([
            'errors' => function () {
                return Session::get('errors')
                    ? Session::get('errors')->getBag('default')->getMessages()
                    : (object) [];
            },
            'available_locales' => fn () => app(LocaleSettingsService::class)->availableLocales(),
            'supported_locales' => fn () => app(LocaleSettingsService::class)->supportedLocales(),
            'locale' => fn () => app()->getLocale(),
            'preferences' => fn () => [
                'locale' => app()->getLocale(),
                'timezone' => Session::has('timezone') ? Session::get('timezone') : config('app.timezone', 'UTC'),
                'theme' => Session::has('theme') ? Session::get('theme') : 'system',
            ],
        ]);

        Inertia::share('flash', function () {
            return [
                'message' => Session::get('message'),
            ];
        });

        Inertia::share('csrf_token', function () {
            return csrf_token();
        });

        Builder::macro('whereLike', function ($attributes, string $search) {
            /** @phpstan-var \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $this */
            /** @phpstan-param array<int,string>|string $attributes */
            /** @phpstan-return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> */
            $attributes = Arr::wrap($attributes);

            $search = trim($search);
            if ($search === '') {
                return $this;
            }

            $grammar = $this->getQuery()->getGrammar();
            $like    = $grammar instanceof PostgresGrammar ? 'ilike' : 'like';

            $terms = preg_split('/\s+/', $search) ?: [$search];

            return $this->where(function ($q) use ($attributes, $terms, $like) {
                foreach ($terms as $term) {
                    $q->where(function ($qq) use ($attributes, $term, $like) {
                        foreach ($attributes as $attr) {
                            if (str_contains($attr, '.')) {
                                [$relation, $relAttr] = explode('.', $attr, 2);
                                $qq->orWhereHas($relation, function ($rq) use ($relAttr, $term, $like) {
                                    $rq->where($relAttr, $like, "%{$term}%");
                                });
                            } else {
                                $qq->orWhere($attr, $like, "%{$term}%");
                            }
                        }
                    });
                }
            });
        });

        Builder::macro('orWhereLike', function ($attributes, string $search) {
            /** @phpstan-var \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $this */
            /** @phpstan-param array<int,string>|string $attributes */
            /** @phpstan-return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> */
            $attributes = Arr::wrap($attributes);
            $search     = trim($search);
            if ($search === '') {
                return $this;
            }

            $grammar = $this->getQuery()->getGrammar();
            $like    = $grammar instanceof PostgresGrammar ? 'ilike' : 'like';

            $terms = preg_split('/\s+/', $search) ?: [$search];

            return $this->orWhere(function ($q) use ($attributes, $terms, $like) {
                foreach ($terms as $term) {
                    $q->where(function ($qq) use ($attributes, $term, $like) {
                        foreach ($attributes as $attr) {
                            if (str_contains($attr, '.')) {
                                [$rel, $relAttr] = explode('.', $attr, 2);
                                $qq->orWhereHas($rel, function ($rq) use ($relAttr, $term, $like) {
                                    $rq->where($relAttr, $like, "%{$term}%");
                                });
                            } else {
                                $qq->orWhere($attr, $like, "%{$term}%");
                            }
                        }
                    });
                }
            });
        });



        Vite::prefetch(concurrency: 3);
    }
}
