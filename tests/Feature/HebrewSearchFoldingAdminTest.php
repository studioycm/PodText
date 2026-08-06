<?php

use App\Enums\UserRole;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\ContentTags\Pages\ListContentTags;
use App\Filament\Resources\HomepageSections\Pages\ListHomepageSections;
use App\Filament\Resources\Transcriptions\Pages\ListTranscriptions;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Models\Transcription;
use App\Models\User;
use App\Support\Slugs\HebrewSlugger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]));
});

/*
|--------------------------------------------------------------------------
| Admin table search folds too
|--------------------------------------------------------------------------
|
| ~80 columns funnel through one vendor emitter, and
| InteractsWithTableQuery::applySearchConstraint short-circuits on
| $this->searchQuery — so a shared macro that sets it reaches all of them
| without eighty bespoke closures.
|
*/

it('finds a pointed episode title from an unpointed admin search', function (): void {
    $match = ContentItem::factory()->create(['title' => 'שָׁלוֹם עוֹלָם']);
    $other = ContentItem::factory()->create(['title' => 'משהו אחר']);

    Livewire::test(ListContentItems::class)
        ->searchTable('שלום')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

it('finds an unpointed episode title from a pointed admin search', function (): void {
    $match = ContentItem::factory()->create(['title' => 'שלום עולם']);

    Livewire::test(ListContentItems::class)
        ->searchTable('שָׁלוֹם')
        ->assertCanSeeTableRecords([$match]);
});

it('folds a relationship column in admin search', function (): void {
    $group = ContentGroup::factory()->create(['title' => 'גְּמָרָא פודקאסט']);
    $match = ContentItem::factory()->for($group)->create(['title' => 'פרק ראשון']);
    $other = ContentItem::factory()->create(['title' => 'פרק אחר']);

    Livewire::test(ListContentItems::class)
        ->searchTable('גמרא')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

it('folds admin search across every resource table that lists hebrew text', function (
    string $page,
    callable $makeMatch,
    string $search,
): void {
    $match = $makeMatch();

    Livewire::test($page)
        ->searchTable($search)
        ->assertCanSeeTableRecords([$match]);
})->with([
    'podcasts' => [
        ListContentGroups::class,
        fn (): ContentGroup => ContentGroup::factory()->create(['title' => 'מַשֶּׁה פודקאסט']),
        'משה',
    ],
    'authors' => [
        ListAuthors::class,
        fn (): Author => Author::factory()->create(['name' => 'מַשֶּׁה רבנו']),
        'משה',
    ],
    'categories' => [
        ListCategories::class,
        fn (): Category => Category::factory()->create(['name' => 'הֲלָכָה']),
        'הלכה',
    ],
    'tags' => [
        ListContentTags::class,
        fn (): ContentTag => ContentTag::findOrCreate('גְּמָרָא', 'content'),
        'גמרא',
    ],
    'homepage sections' => [
        ListHomepageSections::class,
        fn (): HomepageSection => HomepageSection::factory()->create(['name' => 'מַשֶּׁה']),
        'משה',
    ],
    'transcriptions' => [
        ListTranscriptions::class,
        fn (): Transcription => Transcription::factory()->create(['title' => 'שָׁלוֹם']),
        'שלום',
    ],
    'users' => [
        ListUsers::class,
        fn (): User => User::factory()->create(['name' => 'מַשֶּׁה']),
        'משה',
    ],
]);

it('reaches a slug with a pointed search term, since a slug is already its own fold', function (): void {
    $pointed = 'שָׁלוֹם עוֹלָם';
    $match = ContentItem::factory()->create([
        'title' => 'כותרת אחרת לגמרי',
        'slug' => HebrewSlugger::slug($pointed),
    ]);
    $other = ContentItem::factory()->create(['title' => 'משהו', 'slug' => 'other-episode']);

    expect($match->slug)->toBe('שלום-עולם');

    // The slug column has no shadow — it does not need one — but the term must
    // still be folded on its way in, or a pointed search never reaches it.
    Livewire::test(ListContentItems::class)
        ->searchTable($pointed)
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});
