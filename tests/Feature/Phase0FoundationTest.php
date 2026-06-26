<?php

use App\Models\User;
use App\Support\LocaleDirection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('denies guest access to the admin panel', function (): void {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});

it('allows an authenticated administrator to access the admin panel', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});

it('allows guests to access the public panel at the root path', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('app.name'));

    $route = app('router')->getRoutes()->match(Request::create('/'));

    expect($route->getName() ?? '')->toStartWith('filament.public.');
});

it('resolves Hebrew as RTL and English as LTR', function (): void {
    expect(LocaleDirection::forLocale('he'))->toBe('rtl')
        ->and(LocaleDirection::forLocale('en'))->toBe('ltr');

    app()->setLocale('he');

    $this->get('/')
        ->assertOk()
        ->assertSee('dir="rtl"', false);

    app()->setLocale('en');

    $this->get('/')
        ->assertOk()
        ->assertSee('dir="ltr"', false);
});

it('configures Hebrew as the primary locale and English as available', function (): void {
    expect(config('app.locale'))->toBe('he')
        ->and(config('localization.available_locales'))->toBe(['he', 'en']);
});
