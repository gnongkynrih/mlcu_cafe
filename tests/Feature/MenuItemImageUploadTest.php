<?php

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('a new menu item can be saved with an image', function () {
    $category = Category::factory()->create();

    Livewire::test('pages::admin.menu-item-management')
        ->call('create')
        ->set('category_id', $category->id)
        ->set('name', 'Cappuccino')
        ->set('price', '120')
        ->set('image', UploadedFile::fake()->image('cappuccino.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    $menuItem = MenuItem::firstWhere('name', 'Cappuccino');

    expect($menuItem->image)->toStartWith('menu-items/');
    Storage::disk('public')->assertExists($menuItem->image);
});

test('a menu item can be saved without an image', function () {
    $category = Category::factory()->create();

    Livewire::test('pages::admin.menu-item-management')
        ->call('create')
        ->set('category_id', $category->id)
        ->set('name', 'Espresso')
        ->set('price', '90')
        ->call('save')
        ->assertHasNoErrors();

    expect(MenuItem::firstWhere('name', 'Espresso')->image)->toBeNull();
});

test('updating a menu item with a new image replaces the old file', function () {
    $category = Category::factory()->create();
    $oldImage = UploadedFile::fake()->image('old.jpg')->store('menu-items', 'public');
    $menuItem = MenuItem::factory()->create(['category_id' => $category->id, 'image' => $oldImage]);

    Livewire::test('pages::admin.menu-item-management')
        ->call('show', $menuItem->id)
        ->set('image', UploadedFile::fake()->image('new.jpg'))
        ->call('update')
        ->assertHasNoErrors();

    $newImage = $menuItem->fresh()->image;

    expect($newImage)->not->toBe($oldImage);
    Storage::disk('public')->assertExists($newImage);
    Storage::disk('public')->assertMissing($oldImage);
});

test('updating a menu item without picking an image keeps the current image', function () {
    $category = Category::factory()->create();
    $image = UploadedFile::fake()->image('latte.jpg')->store('menu-items', 'public');
    $menuItem = MenuItem::factory()->create(['category_id' => $category->id, 'image' => $image]);

    Livewire::test('pages::admin.menu-item-management')
        ->call('show', $menuItem->id)
        ->set('name', 'Latte')
        ->call('update')
        ->assertHasNoErrors();

    expect($menuItem->fresh()->image)->toBe($image);
    Storage::disk('public')->assertExists($image);
});

test('non image files and images over 2 MB are rejected', function (UploadedFile $file, string $rule) {
    Livewire::test('pages::admin.menu-item-management')
        ->set('image', $file)
        ->assertHasErrors(['image' => $rule]);
})->with([
    'pdf file' => [fn () => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'), 'image'],
    'large image' => [fn () => UploadedFile::fake()->image('huge.jpg')->size(3000), 'max'],
]);

test('the take order page shows menu item images', function () {
    $category = Category::factory()->create();
    $image = UploadedFile::fake()->image('mocha.jpg')->store('menu-items', 'public');
    $menuItem = MenuItem::factory()->create(['category_id' => $category->id, 'image' => $image]);

    session()->put('order', [
        'order_id' => 1,
        'table_id' => 1,
        'table_name' => 'T1',
        'customer_name' => 'Ana',
        'guests' => 2,
        'order_type' => 'dine',
    ]);

    Livewire::test('pages::take-order')
        ->call('showMenuItems')
        ->assertSee($menuItem->image_url);
});
