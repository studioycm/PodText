<?php

namespace App\Enums;

enum PublicFrontConfigBlockType: string
{
    case CardTemplate = 'card_template';
    case MenuItem = 'menu_item';
    case AboutBlock = 'about_block';
    case TeamProfile = 'team_profile';
    case PublicForm = 'public_form';
    case RouteLabel = 'route_label';
}
