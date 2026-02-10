<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/**
 * ======================================
 * SELECTOROK
 * ======================================
 */
Route::middleware(['auth', 'verified', 'throttle:120,1'])->group(function(): void {
    // Company Selector
    Route::get('/selectors/companies', [CompanyController::class, 'getToSelect'])->name('selectors.companies');
    // Employee Selector
    Route::get('/selectors/employees', [EmployeeController::class, 'getToSelect'])->name('selectors.employees');
    // User Selector
    Route::get('/selectors/users', [UserController::class, 'getToSelect'])->name('selectors.users');

    // Permissions Selector
    Route::get('admin/selectors/permissions', [RoleController::class, 'getPermissionsToSelect'])->name('admin.selectors.permissions');
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
Route::middleware(['auth', 'verified'])->group(function () {

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
    Route::get('/shifts', fn () => Inertia::render('HR/Shifts/Index'))->name('shifts.index');
    Route::get('/planning', fn () => Inertia::render('HR/Planning/Index'))->name('planning.index');

    // Beállítások
    Route::get('/settings/app', fn () => Inertia::render('Settings/App/Index'))->name('settings.app');
    Route::get('/settings/company', fn () => Inertia::render('Settings/Company/Index'))->name('settings.company');
    Route::get('/settings/user', fn () => Inertia::render('Settings/User/Index'))->name('settings.user');
});

/**
 * ======================================
 * ADMIN
 * ======================================
 */
//Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
        
        // ---------------------------------------
        // ROLES
        // ---------------------------------------
        
        // Role Selector
        //Route::get('admin/selectors/permissions', [RoleController::class, 'getPermissionsToSelect'])->name('admin.selectors.permissions');
        
        // INDEX
        //Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        // FETCH
        //Route::get('/roles/fetch', [RoleController::class, 'fetch'])->name('roles.fetch');
        // SEARCH
        //Route::get('/roles/{id}', [RoleController::class, 'destroy'])->whereNumber('id')->name('roles.by_id');
        // SEARCH BY NAME
        //Route::get('/roles/name/{name}', [RoleController::class, 'byName'])->where('name', '[A-Za-z0-9_.@\- ]+')->name('roles.by_name');
        // CREATE
        //Route::post('/roles', [RoleController::class, 'destroy'])->name('roles.store');
        // UPDATE
        //Route::put('/roles/{id}', [RoleController::class, 'destroy'])->whereNumber('id')->name('roles.update');
        // DELETE
        //Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('roles.destroy');
        // BULK DELETE
        //Route::delete('/roles/destroy_bulk', [RoleController::class, 'bulkDelete'])->name('roles.destroy_bulk');
//    });
    
Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        
        // ---------------------------------------
        // ROLES
        // ---------------------------------------
        
        // ROLE SELECTOR
        Route::get('/selectors/roles', [RoleController::class, 'getPermissionsToSelect'])->name('selectors.roles');
        // INDEX
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        // FETCH
        Route::get('/roles/fetch', [RoleController::class, 'fetch'])->name('roles.fetch');
        // SEARCH
        Route::get('/roles/{id}', [RoleController::class, 'destroy'])->whereNumber('id')->name('roles.by_id');
        // SEARCH BY NAME
        Route::get('/roles/name/{name}', [RoleController::class, 'byName'])->where('name', '[A-Za-z0-9_.@\- ]+')->name('roles.by_name');
        // CREATE
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        // UPDATE
        Route::put('/roles/{id}', [RoleController::class, 'update'])->whereNumber('id')->name('roles.update');
        // DELETE
        Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->whereNumber('id')->name('roles.destroy');
        // BULK DELETE
        Route::delete('/roles/destroy_bulk', [RoleController::class, 'destroyBulk'])->name('roles.destroy_bulk');
        
        // ---------------------------------------
        // PERMISSION
        // ---------------------------------------
        
        // PERMISSIONS SELECTOR
        Route::get('/selectors/permissions', [PermissionController::class, 'getPermissionsToSelect'])->name('selectors.roles');
        // INDEX
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        // FETCH
        Route::get('/permissions/fetch', [PermissionController::class, 'fetch'])->name('permissions.fetch');
        // SEARCH
        Route::get('/permissions/{id}', [PermissionController::class, 'destroy'])->whereNumber('id')->name('permissions.by_id');
        // SEARCH BY NAME
        Route::get('/permissions/name/{name}', [PermissionController::class, 'byName'])->where('name', '[A-Za-z0-9_.@\- ]+')->name('permissions.by_name');
        // CREATE
        Route::post('/permissions', [PermissionController::class, 'store'])->name('roles.store');
        // UPDATE
        Route::put('/permissions/{id}', [PermissionController::class, 'update'])->whereNumber('id')->name('permissions.update');
        // DELETE
        Route::delete('/permissions/{id}', [PermissionController::class, 'destroy'])->whereNumber('id')->name('permissions.destroy');
        // BULK DELETE
        Route::delete('/permissions/destroy_bulk', [PermissionController::class, 'destroyBulk'])->name('permissions.destroy_bulk');
        
    });
    

/**
 * ======================================
 * USER
 * ======================================
 * Felhasználók kezelése
 */
Route::middleware(['auth', 'verified'])
    ->prefix('companies')
    ->as('companies.')
    ->controller(UserController::class)
    ->group(function () {
        // INDEX
        Route::get('/', 'index')->name('index');
        // FETCH
        Route::get('/fetch', 'fetch')->name('fetch');
        // BULK DESTROY  ✅ legyen ELŐBB
        Route::delete('/destroy_bulk', 'bulkDelete')->name('destroy_bulk');
        // SEARCH BY NAME
        Route::get('/name/{name}', 'byName')->where('name', '[A-Za-z0-9_.@\- ]+')->name('by_name');
        // PASSWORD RESET EMAIL
        Route::post('/{user}/password-reset', 'sendPasswordReset')->name('send_password_reset');
        // CREATE
        Route::post('/', 'store')->name('store');
        // UPDATE
        Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        // GET BY ID
        Route::get('/{id}', 'getUser')->whereNumber('id')->name('by_id');
        // DELETE
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });


/**
 * ======================================
 * COMPANIES
 * ======================================
 * Cégek kezelése
 */
Route::middleware(['auth', 'verified'])->prefix('companies')->as('companies.')->controller(CompanyController::class)->group(function() {
        // INDEX
        Route::get('/', 'index')->name('index');
        // FETCH
        Route::get('/fetch', 'fetch')->name('fetch');        
        // SEARCH
        Route::get('/{id}', 'getCompany')->whereNumber('id')->name('by_id');
        // CREATE
        Route::post('/', 'store')->name('store');
        // UPDATE
        Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        // DELETE
        Route::delete('/{id}', 'destroy')->name('destroy');
        // BULK DELETE
        Route::delete('/destroy_bulk', 'bulkDelete')->name('destroy_bulk');
    });
    
/**
 * ======================================
 * EMPLOYEES
 * ======================================
 * Dolgozók kezelése
 */
Route::middleware(['auth', 'verified'])->prefix('employees')->as('employees.')->controller(EmployeeController::class)->group(function() {
            // INDEX
            Route::get('/', 'index')->name('index');
            // FETCH
            Route::get('/fetch', 'fetch')->name('fetch');
            // SEARCH
            Route::get('/{id}', 'getEmployee')->whereNumber('id')->name('by_id');
            // CREATE
            Route::post('/', 'store')->name('store');
            // UPDATE
            Route::put('/{id}', 'update')->whereNumber('id')->name('update');
            // DELETE
            Route::delete(
                '/{id}', 
                'destroy')
                ->whereNumber('id')
                ->name('destroy');
            // BULK DELETE
            Route::delete(
                '/destroy_bulk', 
                'bulkDelete')->name('destroy_bulk');
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
