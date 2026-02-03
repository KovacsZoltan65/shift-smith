<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\CompanyController;
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

    // User Selector
    Route::get('/selectors/users', [UserController::class, 'getToSelect'])->name('selectors.users');

    // Role Selector
    Route::get('/selectors/roles', [RoleController::class, 'getToSelect'])->name('selectors.roles');

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
    Route::get('/permissions', fn () => Inertia::render('Security/Permissions/Index'))->name('permissions.index');
    //Route::get('/roles', fn () => Inertia::render('Security/Roles/Index'))->name('roles.index');

    // HR
    Route::get(
        '/employees', 
        fn () => Inertia::render('HR/Employees/Index', ['title' => 'Dolgozók'])
    )->name('employees.index');
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
 * ROLES
 * ======================================
 */
Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('roles/fetch', [RoleController::class, 'fetch'])->name('roles.fetch'); // DataTable-hoz
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

/**
 * ======================================
 * USER
 * ======================================
 * Felhasználók kezelése
 */
Route::middleware(['auth', 'verified'])
    ->prefix('users')
    ->as('users.')
    ->controller(UserController::class)
    ->group(function () {
        // INDEX
        Route::get('/', 'index')->name('index');
        // FETCH
        Route::get('/fetch', 'fetch')->name('fetch');

        // SEARCH BY NAME
        Route::get('/name/{name}', 'byName')
            ->where('name', '[A-Za-z0-9_.@\- ]+')
            ->name('by_name');

        Route::get('/{id}', 'getUser')->whereNumber('id')->name('by_id');
        
        // CREATE
        Route::post('/', 'store')->name('store');
        
        // UPDATE
        Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        // DELETE
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');

        // BULK DESTROY
        Route::delete('/destroy-bulk', 'bulkDelete')->name('destroy_bulk');
        
        // PASSWORD RESET EMAIL
        Route::post('/{user}/password-reset', 'sendPasswordReset')->name('send_password_reset');

    });

/**
 * ======================================
 * COMPANIES
 * ======================================
 * Cégek kezelése
 */
Route::middleware(['auth', 'verified'])
    ->prefix('companies')
    ->as('companies.')
    ->controller(CompanyController::class)
    ->group(function() {
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
        Route::delete('/destroy-bulk', 'bulkDelete')->name('destroy_bulk');
        
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
