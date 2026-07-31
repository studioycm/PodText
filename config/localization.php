<?php

return [
    'available_locales' => ['he', 'en'],

    /*
    |--------------------------------------------------------------------------
    | UI Timezone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored normally by Laravel — UTC — and are presented and
    | accepted in this timezone everywhere a human reads or types one: admin
    | tables and forms, public pages, exports, and the Jerusalem-day buckets
    | the editorial dashboard counts on. Read it through App\Support\UiTimezone
    | rather than this key, and never inline the zone name at a call site.
    |
    */

    'ui_timezone' => 'Asia/Jerusalem',
];
