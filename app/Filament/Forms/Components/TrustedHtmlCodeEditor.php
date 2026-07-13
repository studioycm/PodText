<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;

class TrustedHtmlCodeEditor
{
    public static function make(string $name): CodeEditor
    {
        return CodeEditor::make($name)
            ->language(Language::Html)
            ->wrap()
            ->extraAttributes([
                'class' => 'font-mono',
                'dir' => 'ltr',
                'data-trusted-html-code-editor' => 'true',
            ]);
    }
}
