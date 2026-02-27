<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserAssignmentController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\CompanySelectController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmployeeWorkPatternController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkPatternController;
use App\Http\Controllers\WorkScheduleAssignmentController;
use App\Http\Controllers\WorkShiftAssignmentController;
use App\Http\Controllers\WorkShiftController;
use App\Http\Controllers\DashboardController;
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
        
        // Olvasási műveletek
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('throttle:60,1');
        Route::get('/roles/fetch', [RoleController::class, 'fetch'])->name('roles.fetch')->middleware('throttle:60,1');
        Route::get('/roles/{id}', [RoleController::class, 'getRole'])->whereNumber('id')->name('roles.by_id')->middleware('throttle:60,1');
        Route::get('/roles/name/{name}', [RoleController::class, 'getRoleByName'])->where('name', '[A-Za-z0-9_.@\- ]+')->name('roles.by_name')->middleware('throttle:60,1');
        
        // Írási műveletek
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('throttle:20,1');
        Route::put('/roles/{id}', [RoleController::class, 'update'])->whereNumber('id')->name('roles.update')->middleware('throttle:30,1');
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
Route::middleware(['auth', 'verified'])
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
        Route::post('/', 'store')->name('store')->middleware('throttle:20,1');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy')->middleware('throttle:20,1');
    });

/**
 * ======================================
 * SCHEDULING CALENDAR + WORK SCHEDULE ASSIGNMENTS
 * ======================================
 */
Route::middleware(['auth', 'verified', 'ensure.company'])
    ->controller(WorkScheduleAssignmentController::class)
    ->group(function (): void {
        Route::get('/scheduling/calendar', 'calendar')->name('scheduling.calendar')->middleware('throttle:60,1');
        Route::get('/scheduling/calendar/feed', 'feed')->name('scheduling.calendar.feed')->middleware('throttle:120,1');
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

/**
 * ======================================
 */
    
Route::middleware('guest')->group(function () {

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});
    
require __DIR__.'/auth.php';
