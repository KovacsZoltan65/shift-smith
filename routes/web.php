<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
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
 * MENÜPONT NAVIGÁCIÓK
 * ======================================
 */
Route::middleware(['auth', 'verified'])->group(function () {

    // Menü
    Route::get('/menu', fn () => Inertia::render('Menu/Index'))->name('menu.index');

    // Adminisztráció
    Route::get('/companies', fn () => Inertia::render('Companies/Index'))->name('companies.index');

    // Biztonság
    Route::get('/permissions', fn () => Inertia::render('Security/Permissions/Index'))->name('permissions.index');
    Route::get('/roles', fn () => Inertia::render('Security/Roles/Index'))->name('roles.index');

    // HR
    Route::get('/employees', fn () => Inertia::render('HR/Employees/Index'))->name('employees.index');
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
        Route::post('/destroy-bulk', 'destroyBulk')->name('destroy_bulk');
        
        // PASSWORD RESET EMAIL
        Route::post('/{user}/password-reset', 'sendPasswordReset')->name('send_password_reset');

    });

Route::middleware('guest')->group(function () {

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});
    
require __DIR__.'/auth.php';
