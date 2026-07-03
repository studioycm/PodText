<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum HomepageSectionType: string implements HasLabel
{
    case Latest = 'latest';
    case Category = 'category';
    case Tag = 'tag';
    case ContentGroup = 'content_group';
    case TopTranscribers = 'top_transcribers';
    case CuratedQuery = 'curated_query';

    public function getLabel(): string
    {
        return match ($this) {
            self::Latest => __('admin.homepage_section_type.latest'),
            self::Category => __('admin.homepage_section_type.category'),
            self::Tag => __('admin.homepage_section_type.tag'),
            self::ContentGroup => __('admin.homepage_section_type.content_group'),
            self::TopTranscribers => __('admin.homepage_section_type.top_transcribers'),
            self::CuratedQuery => __('admin.homepage_section_type.curated_query'),
        };
    }
}
