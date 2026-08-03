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

    /*
    |--------------------------------------------------------------------------
    | UI Formats
    |--------------------------------------------------------------------------
    |
    | The day-first shapes dates and numbers take everywhere a human reads
    | them, and the locale numbers are grouped for. Read these through
    | App\Support\UiFormats rather than these keys, and never inline a format
    | string or number_format() at a call site.
    |
    */

    'date_format' => 'd/m/Y',

    'date_time_format' => 'd/m/Y H:i',

    'time_format' => 'H:i',

    'number_locale' => 'he',
];
