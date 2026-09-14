<?php

test('guests can visit the about page', function () {
    $response = $this->get(route('about'));

    $response->assertOk();
    $response->assertSee('This is an About Page');
});
