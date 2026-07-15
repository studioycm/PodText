# Concrete Filament Performance Checks

These checks are standalone. Use them directly when reviewing Filament code.

## Heavy Table Column Callback

Bad: a query runs once per rendered row.

```php
TextColumn::make('author_name')
    ->formatStateUsing(fn (Post $record): string => User::find($record->user_id)->name);
```

Good: let the table query load the relation and render loaded state.

```php
$table
    ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('author'))
    ->columns([
        TextColumn::make('author.name'),
    ]);
```

Also flag queries in `state()`, `color()`, `icon()`, `description()`, `url()`, action labels, visibility callbacks, and authorization callbacks.

## Too Many Expensive Columns

Bad: many visible columns, several with callbacks or relationships.

```php
$table->columns([
    TextColumn::make('number'),
    TextColumn::make('customer.name'),
    TextColumn::make('customer.email'),
    TextColumn::make('status')->badge(),
    TextColumn::make('total')->money(),
    TextColumn::make('items_count')->counts('items'),
    TextColumn::make('latest_note')->formatStateUsing(fn (Order $record) => $record->notes()->latest()->value('body')),
    TextColumn::make('assigned_to.name'),
    TextColumn::make('created_at')->dateTime(),
    TextColumn::make('updated_at')->dateTime(),
    TextColumn::make('shipped_at')->dateTime(),
]);
```

Good: keep the default table focused, eager load/count needed data, and make secondary columns toggleable.

```php
$table
    ->modifyQueryUsing(fn (Builder $query): Builder => $query
        ->with(['customer', 'assignedTo'])
        ->withCount('items'))
    ->columns([
        TextColumn::make('number')->searchable(),
        TextColumn::make('customer.name')->searchable(),
        TextColumn::make('status')->badge(),
        TextColumn::make('total')->money(),
        TextColumn::make('items_count'),
        TextColumn::make('assignedTo.name')->toggleable(isToggledHiddenByDefault: true),
        TextColumn::make('updated_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
    ]);
```

## Missing Searchable Columns

Bad: users must browse or paginate to find records.

```php
$table->columns([
    TextColumn::make('name'),
    TextColumn::make('email'),
]);
```

Good: make high-value text columns searchable and confirm indexes/schema before broad search recommendations.

```php
$table->columns([
    TextColumn::make('name')->searchable(),
    TextColumn::make('email')->searchable(),
]);
```

Do not make every column searchable. Prefer fields users actually search.

## Missing Filters For Large Tables

Bad: status/type/date columns are visible but users cannot reduce the result set.

```php
$table->columns([
    TextColumn::make('status')->badge(),
    IconColumn::make('is_active')->boolean(),
    TextColumn::make('created_at')->date(),
]);
```

Good: add targeted filters for columns that reduce the query meaningfully.

```php
$table
    ->columns([
        TextColumn::make('status')->badge(),
        IconColumn::make('is_active')->boolean(),
        TextColumn::make('created_at')->date(),
    ])
    ->filters([
        SelectFilter::make('status')->options(OrderStatus::class),
        TernaryFilter::make('is_active'),
        Filter::make('created_at')
            ->schema([
                DatePicker::make('created_from'),
                DatePicker::make('created_until'),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['created_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                ->when($data['created_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
            ->indicateUsing(fn (array $data): array => array_filter([
                $data['created_from'] ?? null ? 'Created from '.$data['created_from'] : null,
                $data['created_until'] ?? null ? 'Created until '.$data['created_until'] : null,
            ])),
    ]);
```

## Large Static Option List

Bad: a large array is loaded into the page and is hard to use.

```php
Select::make('city')
    ->options(City::query()->pluck('name', 'id')->all());
```

Good: use a searchable relationship or async search pattern.

```php
Select::make('city_id')
    ->relationship('city', 'name')
    ->searchable();
```

Use `preload()` only for small, bounded datasets.

## Relationship Select Not Searchable

Bad: a growing relationship list is not searchable.

```php
Select::make('user_id')
    ->relationship('user', 'name');
```

Good: enable search and choose useful searchable columns when needed.

```php
Select::make('user_id')
    ->relationship('user', 'name')
    ->searchable(['name', 'email']);
```

## Stats Widget Polling Too Often

Bad: aggregate queries repeat on the default or aggressive polling interval.

```php
class SalesStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Revenue', Order::query()->sum('total')),
        ];
    }
}
```

Good: disable or lengthen polling and cache aggregate work.

```php
class SalesStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $revenue = Cache::remember('stats:revenue', now()->addMinutes(5), fn (): int => Order::query()->sum('total'));

        return [
            Stat::make('Revenue', $revenue),
        ];
    }
}
```

## Navigation Badge Not Cached

Bad: the badge query runs on panel page loads.

```php
public static function getNavigationBadge(): ?string
{
    return (string) static::getModel()::query()->where('status', 'pending')->count();
}
```

Good: cache the count and invalidate it when affected records change.

```php
public static function getNavigationBadge(): ?string
{
    return (string) Cache::remember(
        'navigation-badge:orders:pending',
        now()->addMinutes(5),
        fn (): int => static::getModel()::query()->where('status', 'pending')->count(),
    );
}
```

For multi-tenant panels, include tenant/user/panel scope in the key.

## Large Flat Form

Bad: many fields render and hydrate at once with no grouping.

```php
$schema->components([
    TextInput::make('name'),
    TextInput::make('email'),
    TextInput::make('phone'),
    TextInput::make('address_line_1'),
    TextInput::make('address_line_2'),
    TextInput::make('city'),
    TextInput::make('postcode'),
    TextInput::make('vat_number'),
    TextInput::make('notes'),
]);
```

Better UX: group fields into meaningful sections. This does not by itself prove
lower schema-build, HTML, or Livewire hydration cost.

```php
$schema->components([
    Section::make('Contact')
        ->schema([
            TextInput::make('name'),
            TextInput::make('email'),
            TextInput::make('phone'),
        ]),
    Section::make('Billing')
        ->schema([
            TextInput::make('address_line_1'),
            TextInput::make('address_line_2'),
            TextInput::make('city'),
            TextInput::make('postcode'),
            TextInput::make('vat_number'),
        ]),
]);
```

When performance is the problem, measure schema construction, response bytes,
serialized state, rendered controls/DOM, and Livewire update cost. A focused
page or one-record editor may be required to render less state.

## File Upload Missing Constraints

Bad: unconstrained uploads can be slow and unsafe.

```php
FileUpload::make('attachment');
```

Good: define type and size limits.

```php
FileUpload::make('attachment')
    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
    ->maxSize(5120);
```

Queue image processing or conversions when they are expensive.

## Custom-Data Table Doing Unbounded In-Memory Work

Bad: `records()` loads an unbounded source and assumes Filament will search,
filter, sort, or paginate it.

Good: implement each enabled feature inside `records()`, return only the needed
window, and keep selected/action records stable. If the source is a deliberately
small configuration list, document and test that bound.

## Livewire Public State Contains Large Collections

Bad: a component stores large Eloquent collections or full settings payloads in
public properties that are serialized on every update.

Good: store stable scalar identities, query or project server-side, and expose
only the state the interaction needs. Use computed properties for request-local
memoization; use persisted/shared caches only with explicit scope, expiry, and
invalidation.
