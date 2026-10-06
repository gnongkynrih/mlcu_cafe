<?php

use App\Http\Controllers\RegisteredUserController;
use Illuminate\Support\Facades\Route;

// Route::view('/', 'welcome')->name('welcome');

Route::middleware(['auth','role:admin'])->group(function(){
    Route::livewire('/category-management', 'pages::admin.category-management')->name('category-management');
    Route::livewire('/menu-management', 'pages::admin.menu-item-management')->name('menu-management');
    Route::livewire('/table-management', 'pages::admin.table-management')->name('table-management');

});
Route::middleware(['auth','permission:take order'])->group(function () {
    Route::livewire('/', 'pages::dashboard')->name('dashboard');
    Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');
   
    Route::livewire('/take-order', 'pages::⚡take-order')->name('take-order');
    Route::livewire('/select-table', 'pages::⚡select-table')->name('select-table');

    Route::livewire('/cart', 'pages::⚡cart')->name('cart');
    Route::livewire('/checkout', 'pages::⚡checkout')->name('checkout');
    Route::livewire('/orders', 'pages::⚡orders')->name('orders');

    // Only logged-in users can create new accounts (public sign up is off).
    // Later, RBAC will restrict this to admins only.
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    // POST (not GET) so a browser can't accidentally trigger logout
    // by visiting /logout — and so the session can be safely invalidated.
    Route::post('/logout', function () {
        auth()->logout();
        // destroy the session + regenerate CSRF token after logout
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

});

// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::view('dashboard', 'dashboard')->name('dashboard');
// });

require __DIR__.'/settings.php';
