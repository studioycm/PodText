<?php

arch('keeps the dormant legacy-role migration boundary isolated')
    ->expect('App\\Auth\\LegacyRoleBackfill')
    ->toOnlyBeUsedIn('App\\Auth\\LegacyRoleBackfill');
