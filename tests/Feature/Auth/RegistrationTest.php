<?php

use App\Models\User;

test('guests cannot view the registration screen', function () {
    $this->get(route('register'))->assertRedirect(route('login'));
});

test('guests cannot register a user', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('login'));

    $this->assertDatabaseMissing('users', ['email' => 'john@example.com']);
});

test('authenticated users can view the registration screen', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('register'))
        ->assertOk();
});

test('authenticated users can register a new user and stay logged in', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff)
        ->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('register'))
        ->assertSessionHas('status');

    $this->assertDatabaseHas('users', ['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->assertAuthenticatedAs($staff);
});

test('registering a user with an existing email fails', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff)
        ->post(route('register.store'), [
            'name' => 'Copy Cat',
            'email' => $staff->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors('email');

    expect(User::where('email', $staff->email)->count())->toBe(1);
});
