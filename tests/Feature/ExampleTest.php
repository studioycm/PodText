<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('responds successfully on the public homepage', function (): void {
    $this->get('/')->assertSuccessful();
});
