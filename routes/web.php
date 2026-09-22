<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/about', 'pages::about')->name('about');
Route::livewire('/contact-us', 'pages::contact-us')->name('contact-us');
Route::view('/', 'welcome')->name('home');

Route::livewire('/category-management', 'pages::admin.category-management')->name('category-management');
Route::livewire('/menu-management','pages::admin.menu-item-management')->name('menu-management');
Route::livewire('/table-management','pages::admin.table-management')->name('table-management');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
