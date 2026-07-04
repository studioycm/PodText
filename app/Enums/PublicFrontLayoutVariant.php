<?php

namespace App\Enums;

enum PublicFrontLayoutVariant: string
{
    case Cards = 'cards';
    case Rows = 'rows';
    case Compact = 'compact';
    case Comfortable = 'comfortable';
    case Hidden = 'hidden';
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
    case Base = 'base';
    case LargeTitle = 'lg';
}
