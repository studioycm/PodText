<?php

namespace App\Filament\Public\Pages\Concerns;

use Illuminate\Contracts\View\View;

trait HidesPublicPageHeader
{
    public function getHeader(): ?View
    {
        return view('filament.public.pages.empty-page-header');
    }
}
