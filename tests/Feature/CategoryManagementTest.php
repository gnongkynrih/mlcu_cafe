<?php

use App\Models\Category;
use Livewire\Livewire;

test('category management page displays sort order inputs', function () {
    $category = Category::factory()->create(['sort_order' => 3]);

    Livewire::test('pages::admin.category-management')
        ->assertOk()
        ->assertSee('data-flux-input', false)
        ->assertSee((string) $category->sort_order);
});

test('category sort order can be updated', function () {
    $category = Category::factory()->create(['sort_order' => 1]);

    Livewire::test('pages::admin.category-management')
        ->call('updateSortOrder', $category->id, 5)
        ->assertOk();

    expect($category->fresh()->sort_order)->toBe(5);
});

test('category sort order is not updated for negative values', function () {
    $category = Category::factory()->create(['sort_order' => 2]);

    Livewire::test('pages::admin.category-management')
        ->call('updateSortOrder', $category->id, -1)
        ->assertOk();

    expect($category->fresh()->sort_order)->toBe(2);
});
