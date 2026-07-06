<?php

namespace App\Livewire\Public;

use App\Support\PublicFront\Menu\PublicMenuRenderer;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PublicHeader extends Component
{
    public function render(PublicMenuRenderer $renderer): View
    {
        $config = $renderer->config();

        return view('livewire.public.public-header', [
            'enabled' => $config['enabled'],
            'formMounts' => $config['form_mounts'],
            'itemsAlignment' => $config['items_alignment'],
            'items' => $config['items'],
            'logo' => $config['logo'],
            'search' => $config['search'],
            'themeSelector' => $config['theme_selector'],
        ]);
    }
}
