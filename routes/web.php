<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\AppSettingController;
use App\Http\Controllers\Admin\AbsenceController;
use App\Http\Controllers\Admin\CompanySettingController;
use App\Http\Controllers\Admin\LeaveCategoryController;
use App\Http\Controllers\Admin\LeaveTypeController;
use App\Http\Controllers\Admin\SickLeaveCategoryController;
use App\Http\Controllers\Admin\UserSettingController;
use App\Http\Controllers\Admin\EmployeeLeaveEntitlementController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RoleUsersController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserAssignmentController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\CompanySelectController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmployeeWorkPatternController;
use App\Http\Controllers\EmployeeSupervisorController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkPatternController;
use App\Http\Controllers\WorkScheduleController;
use App\Http\Controllers\WorkScheduleAssignmentController;
use App\Http\Controllers\WorkShiftAssignmentController;
use App\Http\Controllers\WorkShiftController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonthClosureController;
use App\Http\Controllers\HR\OrgHierarchyController;
use App\Http\Controllers\OrgPositionLevelController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'ensure.company'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/select-company', [CompanySelectController::class, 'index'])->name('company.select');
    Route::post('/select-company', [CompanySelectController::class, 'store'])->name('company.select.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/**
 * ======================================
 * SELECTOROK
 * ======================================
 */
Route::middleware(['auth', 'verified', 'ensure.company', 'throttle:120,1'])->group(function(): void {
    // Company Selector
    Route::get('/selectors/companies', [CompanyController::class, 'getToSelect'])->name('selectors.companies');
    // Employee Selector
    Route::get('/selectors/employees', [EmployeeController::class, 'getToSelect'])->name('selectors.employees');
    // Position Selector
    Route::get('/selectors/positions', [PositionController::class, 'getToSelect'])->name('selectors.positions');
    // WorkShift Selector
    Route::get('/selectors/work_shifts', [WorkShiftController::class, 'getToSelect'])->name('selectors.work_shifts');
    // User Selector
    Route::get('/selectors/users', [UserController::class, 'getToSelect'])->name('selectors.users');
    // WorkPattern Selector
    Route::get('/selectors/work-patterns', [WorkPatternController::class, 'getToSelect'])->name('selectors.work_patterns');

    // (Admin selectorok az /admin prefix alatt vannak, lent)
});

//Route::get('/_debug/csrf', function() {
//    $sessionCookie = config('session.cookie');
//
//    return response()->json([
//        'session_cookie_name' => $sessionCookie,
//        'session_id' => session()->getId(),
//        'token_session' => csrf_token(),
//        'token_header' => request()->header('x-csrf-token'),
//        'token_input' => request()->input('_token'),
//        'cookies' => [
//            'session_cookie' => request()->cookie($sessionCookie),
//            'XSRF-TOKEN' => request()->cookie('XSRF-TOKEN'),
//        ],
//    ]);
//});

/**
 * ======================================
 * MENÜPONT NAVIGÁCIÓK
 * ======================================
 */
Route::middleware(['auth', 'verified', 'ensure.company'])->group(function () {

    // Menü
    Route::get('/menu', fn () => Inertia::render('Menu/Index'))->name('menu.index');

    // Users
    // Az "Users" route -ok között
    
    // Adminisztráció
    // A "Companies" route -ok között
    //Route::get('/companies', fn () => Inertia::render('Companies/Index', ['title' => 'Cégek']))->name('companies.index');

    // Biztonság
    //Route::get('/permissions', fn () => Inertia::render('Security/Permissions/Index'))->name('permissions.index');
    //Route::get('/roles', fn () => Inertia::render('Security/Roles/Index'))->name('roles.index');

    // HR
    //Route::get('/employees', fn () => Inertia::render('HR/Employees/Index', ['title' => 'Dolgozók']))->name('employees.index');
    
    Route::get('/assignments', fn () => Inertia::render('HR/Assignments/Index'))->name('assignments.index');
    Route::get('/hr/hierarchy', [OrgHierarchyController::class, 'index'])
        ->name('org.hierarchy.index')
        ->middleware('throttle:60,1');
    Route::get('/hr/hierarchy/graph', [OrgHierarchyController::class, 'graph'])
        ->name('org.hierarchy.graph')
        ->middleware('throttle:120,1');
    Route::get('/hr/hierarchy/node/{id}', [OrgHierarchyController::class, 'node'])
        ->whereNumber('id')
        ->name('org.hierarchy.node')
        ->middleware('throttle:120,1');
    Route::get('/hr/hierarchy/employees/search', [OrgHierarchyController::class, 'employeesSearch'])
        ->name('org.hierarchy.employees.search')
        ->middleware('throttle:120,1');
    Route::get('/hr/hierarchy/path', [OrgHierarchyController::class, 'path'])
        ->name('org.hierarchy.path')
        ->middleware('throttle:120,1');
    Route::get('/hr/hierarchy/move/preview', [OrgHierarchyController::class, 'movePreview'])
        ->name('org.hierarchy.move.preview')
        ->middleware('throttle:60,1');
    Route::post('/hr/hierarchy/move', [OrgHierarchyController::class, 'move'])
        ->name('org.hierarchy.move')
        ->middleware('throttle:20,1');
    Route::get('/hr/hierarchy/integrity', [OrgHierarchyController::class, 'integrity'])
        ->name('org.hierarchy.integrity')
        ->middleware('throttle:60,1');
    Route::post('/hr/hierarchy/design-settings', [OrgHierarchyController::class, 'saveDesignSettings'])
        ->name('org.hierarchy.design_settings.save')
        ->middleware('throttle:30,1');

    // Beállítások
    Route::get('/settings/app', [SettingsController::class, 'app'])->name('settings.app');
    Route::get('/settings/company', [SettingsController::class, 'company'])->name('settings.company');
    Route::get('/settings/user', [SettingsController::class, 'user'])->name('settings.user');
    Route::get('/settings/fetch', [SettingsController::class, 'fetch'])->name('settings.fetch')->middleware('throttle:60,1');
    Route::post('/settings/save', [SettingsController::class, 'save'])->name('settings.save')->middleware('throttle:20,1');
});

/**
 * ======================================
 * ADMIN
 * ======================================
 */ 
Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        
        // ---------------------------------------
        // ROLES
        // ---------------------------------------
        
        // SELECTOROK
        Route::get('/selectors/roles', [RoleController::class, 'getToSelect'])->name('selectors.roles')->middleware('throttle:120,1');
        Route::get('/selectors/permissions', [PermissionController::class, 'getToSelect'])->name('selectors.permissions')->middleware('throttle:120,1');
        Route::get('/selectors/users', [UserController::class, 'getToSelect'])->name('selectors.users')->middleware('throttle:120,1');
        
        // Olvasási műveletek
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('throttle:60,1');
        Route::get('/roles/fetch', [RoleController::class, 'fetch'])->name('roles.fetch')->middleware('throttle:60,1');
        Route::get('/roles/{id}', [RoleController::class, 'getRole'])->whereNumber('id')->name('roles.by_id')->middleware('throttle:60,1');
        Route::get('/roles/name/{name}', [RoleController::class, 'getRoleByName'])->where('name', '[A-Za-z0-9_.@\- ]+')->name('roles.by_name')->middleware('throttle:60,1');
        
        // Írási műveletek
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('throttle:20,1');
        Route::put('/roles/{id}', [RoleController::class, 'update'])->whereNumber('id')->name('roles.update')->middleware('throttle:30,1');
        Route::patch('/roles/{role}/users', [RoleUsersController::class, 'update'])->whereNumber('role')->name('roles.users.update')->middleware('throttle:20,1');
        Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->whereNumber('id')->name('roles.destroy')->middleware('throttle:20,1');
        Route::delete('/roles/destroy_bulk', [RoleController::class, 'destroyBulk'])->name('roles.destroy_bulk')->middleware('throttle:10,1');
        
        // ---------------------------------------
        // PERMISSION
        // ---------------------------------------
        
        // Olvasási műveletek
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index')->middleware('throttle:60,1');
        Route::get('/permissions/fetch', [PermissionController::class, 'fetch'])->name('permissions.fetch')->middleware('throttle:60,1');
        Route::get('/permissions/{id}', [PermissionController::class, 'getPermission'])->whereNumber('id')->name('permissions.by_id')->middleware('throttle:60,1');
        Route::get('/permissions/name/{name}', [PermissionController::class, 'getPermissionByName'])->where('name', '[A-Za-z0-9_.@\- ]+')->name('permissions.by_name')->middleware('throttle:60,1');
        
        // Írási műveletek
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store')->middleware('throttle:20,1');
        Route::put('/permissions/{id}', [PermissionController::class, 'update'])->whereNumber('id')->name('permissions.update')->middleware('throttle:30,1');
        Route::delete('/permissions/{id}', [PermissionController::class, 'destroy'])->whereNumber('id')->name('permissions.destroy')->middleware('throttle:20,1');
        Route::delete('/permissions/destroy_bulk', [PermissionController::class, 'destroyBulk'])->name('permissions.destroy_bulk')->middleware('throttle:10,1');

        Route::get('/app-settings', [AppSettingController::class, 'index'])->name('app_settings.index')->middleware('throttle:60,1');
        Route::get('/app-settings/fetch', [AppSettingController::class, 'fetch'])->name('app_settings.fetch')->middleware('throttle:60,1');
        Route::get('/app-settings/{id}', [AppSettingController::class, 'show'])->whereNumber('id')->name('app_settings.show')->middleware('throttle:60,1');
        Route::post('/app-settings', [AppSettingController::class, 'store'])->name('app_settings.store')->middleware('throttle:20,1');
        Route::put('/app-settings/{id}', [AppSettingController::class, 'update'])->whereNumber('id')->name('app_settings.update')->middleware('throttle:30,1');
        Route::delete('/app-settings/destroy-bulk', [AppSettingController::class, 'bulkDestroy'])->name('app_settings.destroy_bulk')->middleware('throttle:10,1');
        Route::delete('/app-settings/{id}', [AppSettingController::class, 'destroy'])->whereNumber('id')->name('app_settings.destroy')->middleware('throttle:20,1');

        Route::middleware(['ensure.company'])->group(function (): void {
            Route::get('/position-org-levels', [OrgPositionLevelController::class, 'index'])->name('position_org_levels.index')->middleware('throttle:60,1');
            Route::get('/position-org-levels/fetch', [OrgPositionLevelController::class, 'fetch'])->name('position_org_levels.fetch')->middleware('throttle:60,1');
            Route::post('/position-org-levels', [OrgPositionLevelController::class, 'store'])->name('position_org_levels.store')->middleware('throttle:20,1');
            Route::put('/position-org-levels/{id}', [OrgPositionLevelController::class, 'update'])->whereNumber('id')->name('position_org_levels.update')->middleware('throttle:30,1');
            Route::delete('/position-org-levels/{id}', [OrgPositionLevelController::class, 'destroy'])->whereNumber('id')->name('position_org_levels.destroy')->middleware('throttle:20,1');

            Route::get('/leave-types', [LeaveTypeController::class, 'index'])->name('leave_types.index')->middleware('throttle:60,1');
            Route::get('/leave-types/fetch', [LeaveTypeController::class, 'fetch'])->name('leave_types.fetch')->middleware('throttle:60,1');
            Route::get('/leave-types/selector', [LeaveTypeController::class, 'selector'])->name('leave_types.selector')->middleware('throttle:120,1');
            Route::post('/leave-types', [LeaveTypeController::class, 'store'])->name('leave_types.store')->middleware('throttle:20,1');
            Route::get('/leave-types/{id}', [LeaveTypeController::class, 'show'])->whereNumber('id')->name('leave_types.show')->middleware('throttle:60,1');
            Route::put('/leave-types/{id}', [LeaveTypeController::class, 'update'])->whereNumber('id')->name('leave_types.update')->middleware('throttle:30,1');
            Route::delete('/leave-types/{id}', [LeaveTypeController::class, 'destroy'])->whereNumber('id')->name('leave_types.destroy')->middleware('throttle:20,1');

            Route::get('/leave-categories', [LeaveCategoryController::class, 'index'])->name('leave_categories.index')->middleware('throttle:60,1');
            Route::get('/leave-categories/fetch', [LeaveCategoryController::class, 'fetch'])->name('leave_categories.fetch')->middleware('throttle:60,1');
            Route::get('/leave-categories/selector', [LeaveCategoryController::class, 'selector'])->name('leave_categories.selector')->middleware('throttle:120,1');
            Route::post('/leave-categories', [LeaveCategoryController::class, 'store'])->name('leave_categories.store')->middleware('throttle:20,1');
            Route::get('/leave-categories/{id}', [LeaveCategoryController::class, 'show'])->whereNumber('id')->name('leave_categories.show')->middleware('throttle:60,1');
            Route::put('/leave-categories/{id}', [LeaveCategoryController::class, 'update'])->whereNumber('id')->name('leave_categories.update')->middleware('throttle:30,1');
            Route::delete('/leave-categories/{id}', [LeaveCategoryController::class, 'destroy'])->whereNumber('id')->name('leave_categories.destroy')->middleware('throttle:20,1');

            Route::get('/sick-leave-categories', [SickLeaveCategoryController::class, 'index'])->name('sick_leave_categories.index')->middleware('throttle:60,1');
            Route::get('/sick-leave-categories/fetch', [SickLeaveCategoryController::class, 'fetch'])->name('sick_leave_categories.fetch')->middleware('throttle:60,1');
            Route::get('/sick-leave-categories/selector', [SickLeaveCategoryController::class, 'selector'])->name('sick_leave_categories.selector')->middleware('throttle:120,1');
            Route::post('/sick-leave-categories', [SickLeaveCategoryController::class, 'store'])->name('sick_leave_categories.store')->middleware('throttle:20,1');
            Route::get('/sick-leave-categories/{id}', [SickLeaveCategoryController::class, 'show'])->whereNumber('id')->name('sick_leave_categories.show')->middleware('throttle:60,1');
            Route::put('/sick-leave-categories/{id}', [SickLeaveCategoryController::class, 'update'])->whereNumber('id')->name('sick_leave_categories.update')->middleware('throttle:30,1');
            Route::delete('/sick-leave-categories/{id}', [SickLeaveCategoryController::class, 'destroy'])->whereNumber('id')->name('sick_leave_categories.destroy')->middleware('throttle:20,1');

            Route::get('/absences/fetch', [AbsenceController::class, 'fetch'])->name('absences.fetch')->middleware('throttle:60,1');
            Route::post('/absences', [AbsenceController::class, 'store'])->name('absences.store')->middleware('throttle:20,1');
            Route::get('/absences/{id}', [AbsenceController::class, 'show'])->whereNumber('id')->name('absences.show')->middleware('throttle:60,1');
            Route::put('/absences/{id}', [AbsenceController::class, 'update'])->whereNumber('id')->name('absences.update')->middleware('throttle:30,1');
            Route::delete('/absences/{id}', [AbsenceController::class, 'destroy'])->whereNumber('id')->name('absences.destroy')->middleware('throttle:20,1');

            Route::get('/company-settings', [CompanySettingController::class, 'index'])->name('company_settings.index')->middleware('throttle:60,1');
            Route::get('/company-settings/fetch', [CompanySettingController::class, 'fetch'])->name('company_settings.fetch')->middleware('throttle:60,1');
            Route::get('/company-settings/effective', [CompanySettingController::class, 'effective'])->name('company_settings.effective')->middleware('throttle:60,1');
            Route::get('/company-settings/{id}', [CompanySettingController::class, 'show'])->whereNumber('id')->name('company_settings.show')->middleware('throttle:60,1');
            Route::post('/company-settings', [CompanySettingController::class, 'store'])->name('company_settings.store')->middleware('throttle:20,1');
            Route::put('/company-settings/{id}', [CompanySettingController::class, 'update'])->whereNumber('id')->name('company_settings.update')->middleware('throttle:30,1');
            Route::delete('/company-settings/destroy-bulk', [CompanySettingController::class, 'bulkDestroy'])->name('company_settings.destroy_bulk')->middleware('throttle:10,1');
            Route::delete('/company-settings/{id}', [CompanySettingController::class, 'destroy'])->whereNumber('id')->name('company_settings.destroy')->middleware('throttle:20,1');

            Route::get('/user-settings', [UserSettingController::class, 'index'])->name('user_settings.index')->middleware('throttle:60,1');
            Route::get('/user-settings/fetch', [UserSettingController::class, 'fetch'])->name('user_settings.fetch')->middleware('throttle:60,1');
            Route::get('/user-settings/{id}', [UserSettingController::class, 'show'])->whereNumber('id')->name('user_settings.show')->middleware('throttle:60,1');
            Route::post('/user-settings', [UserSettingController::class, 'store'])->name('user_settings.store')->middleware('throttle:20,1');
            Route::put('/user-settings/{id}', [UserSettingController::class, 'update'])->whereNumber('id')->name('user_settings.update')->middleware('throttle:30,1');
            Route::delete('/user-settings/destroy-bulk', [UserSettingController::class, 'bulkDestroy'])->name('user_settings.destroy_bulk')->middleware('throttle:10,1');
            Route::delete('/user-settings/{id}', [UserSettingController::class, 'destroy'])->whereNumber('id')->name('user_settings.destroy')->middleware('throttle:20,1');
        });

        // ---------------------------------------
        // USER <-> EMPLOYEE MAPPING
        // ---------------------------------------
        Route::middleware(['ensure.company'])->group(function (): void {
            Route::get('/user-assignments', [UserAssignmentController::class, 'index'])->name('user_assignments.index')->middleware('throttle:60,1');
            Route::get('/user-assignments/fetch/users', [UserAssignmentController::class, 'fetchUsers'])->name('user_assignments.users.fetch')->middleware('throttle:60,1');
            Route::get('/user-assignments/{user}/fetch', [UserAssignmentController::class, 'fetch'])->name('user_assignments.fetch')->middleware('throttle:60,1');
            Route::post('/user-assignments/{user}/companies', [UserAssignmentController::class, 'attachCompany'])->name('user_assignments.companies.store')->middleware('throttle:20,1');
            Route::delete('/user-assignments/{user}/companies/{company}', [UserAssignmentController::class, 'detachCompany'])->name('user_assignments.companies.destroy')->middleware('throttle:20,1');
            Route::post('/user-assignments/{user}/companies/{company}/employee', [UserAssignmentController::class, 'assignEmployee'])->name('user_assignments.employee.assign')->middleware('throttle:20,1');
            Route::delete('/user-assignments/{user}/companies/{company}/employee', [UserAssignmentController::class, 'removeEmployee'])->name('user_assignments.employee.destroy')->middleware('throttle:20,1');
        });
        
    });
    

/**
 * ======================================
 * USER
 * ======================================
 * Felhasználók kezelése
 */
Route::middleware(['auth', 'verified', 'ensure.company'])
        ->prefix('users')->as('users.')->controller(UserController::class)->group(function () {
        // INDEX - olvasási műveletek
        Route::get('/', 'index')->name('index')->middleware('throttle:60,1');
        // FETCH
        Route::get('/fetch', 'fetch')->name('fetch')->middleware('throttle:60,1');
        // GET BY ID
        Route::get('/{id}', 'getUser')->whereNumber('id')->name('by_id')->middleware('throttle:60,1');
        // SEARCH BY NAME
        Route::get('/name/{name}', 'byName')->where('name', '[A-Za-z0-9_.@\- ]+')->name('by_name')->middleware('throttle:60,1');
        
        // Írási műveletek - szigorúbb limit
        // CREATE
        Route::post('/', 'store')->name('store')->middleware('throttle:20,1');
        // UPDATE
        Route::put('/{id}', 'update')->whereNumber('id')->name('update')->middleware('throttle:30,1');
        // DELETE
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')->middleware('throttle:20,1');
        // BULK DESTROY
        Route::delete('/destroy_bulk', 'bulkDelete')->name('destroy_bulk')->middleware('throttle:10,1');
        // PASSWORD RESET EMAIL - nagyon szigorú limit
        Route::post('/{user}/password-reset', 'sendPasswordReset')->name('send_password_reset')->middleware('throttle:5,1');
    });

Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('admin/users')
    ->name('admin.users.')
    ->group(function (): void {
        Route::patch('/{user}/role', [UserRoleController::class, 'update'])
            ->whereNumber('user')
            ->name('role.update')
            ->middleware('throttle:20,1');
    });


/**
 * ======================================
 * COMPANIES
 * ======================================
 * Cégek kezelése
 */
Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('companies')
    ->as('companies.')
    ->controller(CompanyController::class)
    ->group(function() {
        // Olvasási műveletek
        Route::get('/', 'index')->name('index')->middleware('throttle:60,1');
        Route::get('/fetch', 'fetch')->name('fetch')->middleware('throttle:60,1');
        Route::get('/{id}', 'getCompany')->whereNumber('id')->name('by_id')->middleware('throttle:60,1');
        
        // Írási műveletek
        Route::post('/', 'store')->name('store')->middleware('throttle:20,1');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update')->middleware('throttle:30,1');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')->middleware('throttle:20,1');
        Route::delete('/destroy_bulk', 'bulkDelete')->name('destroy_bulk')->middleware('throttle:10,1');
    });

/**
 * ======================================
 * HQ COMPANIES (landlord/global)
 * ======================================
 */
Route::middleware(['auth', 'verified', 'superadmin', 'hq.landlord'])
    ->prefix('hq/companies')
    ->as('hq.companies.')
    ->controller(\App\Http\Controllers\Hq\CompanyController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index')->middleware('throttle:60,1');
        Route::get('/fetch', 'fetch')->name('fetch')->middleware('throttle:60,1');
        Route::get('/{id}', 'getCompany')->whereNumber('id')->name('by_id')->middleware('throttle:60,1');
    });
    
/**
 * ======================================
 * EMPLOYEES
 * ======================================
 * Dolgozók kezelése
 */
Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('positions')
    ->as('positions.')
    ->controller(PositionController::class)
    ->group(function() {
        Route::get('/', 'index')->name('index')->middleware('throttle:60,1');
        Route::get('/fetch', 'fetch')->name('fetch')->middleware('throttle:60,1');
        Route::get('/{id}', 'getPosition')->whereNumber('id')->name('by_id')->middleware('throttle:60,1');

        Route::post('/', 'store')->name('store')->middleware('throttle:20,1');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update')->middleware('throttle:30,1');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')->middleware('throttle:20,1');
        Route::delete('/destroy_bulk', 'bulkDelete')->name('destroy_bulk')->middleware('throttle:10,1');
    });

Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('employees')
    ->as('employees.')
    ->controller(EmployeeController::class)
    ->group(function() {
        // Olvasási műveletek
        Route::get('/', 'index')->name('index')->middleware('throttle:60,1');
        Route::get('/fetch', 'fetch')->name('fetch')->middleware('throttle:60,1');
        Route::get('/selector', 'selector')->name('selector')->middleware('throttle:120,1');
        Route::get('/{id}', 'getEmployee')->whereNumber('id')->name('by_id')->middleware('throttle:60,1');
        
        // Írási műveletek
        Route::post('/', 'store')->name('store')->middleware('throttle:20,1');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update')->middleware('throttle:30,1');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')->middleware('throttle:20,1');
        Route::delete('/destroy_bulk', 'bulkDelete')->name('destroy_bulk')->middleware('throttle:10,1');
    });

Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('employees')
    ->as('employees.')
    ->controller(EmployeeSupervisorController::class)
    ->group(function (): void {
        Route::post('/{employee}/supervisor', 'assign')->whereNumber('employee')->name('supervisor.assign')->middleware('throttle:20,1');
    });

Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('admin/employees')
    ->as('employees.')
    ->controller(\App\Http\Controllers\Admin\EmployeeLeaveProfileController::class)
    ->group(function (): void {
        Route::get('/{id}/leave-profile', 'show')->whereNumber('id')->name('leave_profile.show')->middleware('throttle:60,1');
        Route::put('/{id}/leave-profile', 'update')->whereNumber('id')->name('leave_profile.update')->middleware('throttle:30,1');
    });

Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('admin/employees')
    ->as('employees.')
    ->controller(EmployeeLeaveEntitlementController::class)
    ->group(function (): void {
        Route::get('/{id}/leave-entitlement', 'show')->whereNumber('id')->name('leave_entitlement')->middleware('throttle:60,1');
    });

/**
 * ======================================
 * EMPLOYEE WORK PATTERNS
 * ======================================
 */
Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('employees/{employee}/work-patterns')
    ->whereNumber('employee')
    ->as('employee_work_patterns.')
    ->controller(EmployeeWorkPatternController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index')->middleware('throttle:60,1');
        Route::post('/assign', 'assign')->name('assign')->middleware('throttle:20,1');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update')->middleware('throttle:30,1');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')->middleware('throttle:20,1');
    });
    
/**
 * ======================================
 * WORK_SHIFTS
 * ======================================
 * Műszakok kezelése
 */
Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('work-shifts')
    ->as('work_shifts.')
    ->controller(WorkShiftController::class)
    ->group(function() {
    // Olvasási műveletek
    Route::get('/', 'index')->name('index')->middleware('throttle:60,1');
    Route::get('/fetch', 'fetch')->name('fetch')->middleware('throttle:60,1');
    Route::get('/{id}', 'getWorkShift')->whereNumber('id')->name('by_id')->middleware('throttle:60,1');
    
    // Írási műveletek
    Route::post('/', 'store')->name('store')->middleware('throttle:20,1');
    Route::put('/{id}', 'update')->whereNumber('id')->name('update')->middleware('throttle:30,1');
    Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')->middleware('throttle:20,1');
    Route::delete('/destroy_bulk', 'bulkDelete')->name('destroy_bulk')->middleware('throttle:10,1');
});

/**
 * ======================================
 * WORK_SHIFT ASSIGNMENTS
 * ======================================
 */
Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('work_shifts/{work_shift}/assignments')
    ->whereNumber('work_shift')
    ->as('work_shift_assignments.')
    ->controller(WorkShiftAssignmentController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index')->middleware('throttle:60,1');
        Route::get('/schedules', 'schedules')->name('schedules')->middleware('throttle:60,1');
        Route::post('/', 'store')->name('store')->middleware('throttle:20,1');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')->middleware('throttle:20,1');
    });

/**
 * ======================================
 * SCHEDULING CALENDAR + WORK SCHEDULE ASSIGNMENTS
 * ======================================
 */
Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('work-schedules')
    ->as('work_schedules.')
    ->controller(WorkScheduleController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index')->middleware('throttle:60,1');
        Route::get('/fetch', 'fetch')->name('fetch')->middleware('throttle:60,1');
        Route::get('/selector', 'selector')->name('selector')->middleware('throttle:120,1');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show')->middleware('throttle:60,1');
        Route::post('/', 'store')->name('store')->middleware('throttle:20,1');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update')->middleware('throttle:30,1');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')->middleware('throttle:20,1');
        Route::delete('/destroy_bulk', 'destroyBulk')->name('destroy_bulk')->middleware('throttle:10,1');
    });

Route::middleware(['auth', 'verified', 'ensure.company'])
    ->controller(WorkScheduleAssignmentController::class)
    ->group(function (): void {
        Route::get('/scheduling/calendar', 'calendar')->name('scheduling.calendar')->middleware('throttle:60,1');
        Route::get('/scheduling/calendar/feed', 'feed')->name('scheduling.calendar.feed')->middleware('throttle:120,1');
    });

Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('scheduling/month-closures')
    ->controller(MonthClosureController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('scheduling.month_closures.store')->middleware('throttle:20,1');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('scheduling.month_closures.destroy')->middleware('throttle:20,1');
    });

Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('work-schedule-assignments')
    ->controller(WorkScheduleAssignmentController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('work_schedule_assignments.store')->middleware('throttle:20,1');
        Route::put('/{id}', 'update')->whereNumber('id')->name('work_schedule_assignments.update')->middleware('throttle:30,1');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('work_schedule_assignments.destroy')->middleware('throttle:20,1');
        Route::post('/bulk-upsert', 'bulkUpsert')->name('work_schedule_assignments.bulk_upsert')->middleware('throttle:30,1');
    });

/**
 * ======================================
 * WORK_PATTERNS
 * ======================================
 */
Route::middleware(['auth', 'verified', 'ensure.company'])
    ->prefix('work-patterns')
    ->as('work_patterns.')
    ->controller(WorkPatternController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index')->middleware('throttle:60,1');
        Route::get('/fetch', 'fetch')->name('fetch')->middleware('throttle:60,1');
        Route::get('/selector', 'getToSelect')->name('selector')->middleware('throttle:120,1');
        Route::get('/{id}/employees', 'getEmployees')->whereNumber('id')->name('employees')->middleware('throttle:60,1');
        Route::get('/{id}', 'getWorkPattern')->whereNumber('id')->name('by_id')->middleware('throttle:60,1');

        Route::post('/', 'store')->name('store')->middleware('throttle:20,1');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update')->middleware('throttle:30,1');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')->middleware('throttle:20,1');
        Route::delete('/destroy_bulk', 'destroyBulk')->name('destroy_bulk')->middleware('throttle:10,1');
    });

require __DIR__.'/auth.php';
