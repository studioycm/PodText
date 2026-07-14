<?php

namespace App\Filament\Forms\Components;

use App\Support\PublicFront\Icons\PublicFrontIconRegistry;
use Filament\Forms\Components\Select;

class IconSelect
{
    public static function make(string $name): Select
    {
        return Select::make($name)
            ->allowHtml()
            ->searchable()
            ->preload(false)
            ->optionsLimit(50)
            ->searchPrompt(__('admin.helpers.icon_select_search_prompt'))
            ->getSearchResultsUsing(fn (?string $search): array => PublicFrontIconRegistry::searchResults($search))
            ->getOptionLabelUsing(fn (mixed $value): ?string => PublicFrontIconRegistry::optionLabel($value))
            ->native(false);
    }
}
