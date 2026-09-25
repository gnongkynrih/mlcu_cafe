<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('welcome');

Route::middleware(['auth'])->group(function(){
    Route::livewire('/dashboard','pages::dashboard')->name('dashboard');
    Route::livewire('/category-management', 'pages::admin.category-management')->name('category-management');    
    Route::livewire('/menu-management','pages::admin.menu-item-management')->name('menu-management');
    Route::livewire('/table-management','pages::admin.table-management')->name('table-management');


     // POST (not GET) so a browser can't accidentally trigger logout
    // by visiting /logout — and so the session can be safely invalidated.
    Route::post('/logout', function(){
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
