<?php

namespace App\Livewire\Admin;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class DisabledVendorCuratorSurface extends Component
{
    public function mount(): never
    {
        abort(404);
    }

    public function render(): View
    {
        abort(404);
    }
}
