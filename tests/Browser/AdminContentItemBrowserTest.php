<?php

use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create());
});

it('renders the content item edit tabs and core item fields in a real browser', function (): void {
    $item = ContentItem::factory()->create([
        'title' => 'Browser Admin Item',
        'slug' => 'browser-admin-item',
        'media_url' => 'https://example.com/browser-admin-item.mp3',
    ]);

    visit(ContentItemResource::getUrl('edit', ['record' => $item]))
        ->assertNoSmoke()
        ->assertSee(__('admin.tabs.item_details'))
        ->assertSee(__('admin.fields.title'))
        ->assertSee(__('admin.fields.slug'))
        ->assertSee(__('admin.fields.content_group'))
        ->assertSee(__('admin.fields.status'))
        ->assertSee(__('admin.fields.media_url'))
        ->assertSee(__('admin.tabs.transcriptions'))
        ->assertNoJavaScriptErrors();
});
