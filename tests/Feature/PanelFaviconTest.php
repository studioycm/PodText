<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('configures the svg favicon on the admin and public panels', function (): void {
    expect(Filament::getPanel('admin')->getFavicon())->toBe(asset('favicon.svg'))
        ->and(Filament::getPanel('public')->getFavicon())->toBe(asset('favicon.svg'));
});

it('ships the favicon file with theme-aware colors', function (): void {
    $path = public_path('favicon.svg');

    expect(file_exists($path))->toBeTrue();

    $svg = file_get_contents($path);

    expect($svg)->toContain('prefers-color-scheme: dark')
        ->toContain('viewBox="0 0 512 512"');
});

it('renders the favicon link on admin and public panel pages', function (): void {
    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertSee('favicon.svg');

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('favicon.svg');
});
