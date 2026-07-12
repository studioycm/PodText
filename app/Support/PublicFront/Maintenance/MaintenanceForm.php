<?php

namespace App\Support\PublicFront\Maintenance;

class MaintenanceForm
{
    public const MARKER = '<div data-podtext-maintenance-form></div>';

    public const LOCATION_RENDERED_PAGE = 'rendered_page';

    public const LOCATION_RAW_HTML = 'raw_html';

    public const POSITION_BEFORE_CONTENT = 'before_content';

    public const POSITION_AFTER_CONTENT = 'after_content';

    /**
     * @return array<int, string>
     */
    public static function locations(): array
    {
        return [
            self::LOCATION_RENDERED_PAGE,
            self::LOCATION_RAW_HTML,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function locationOptions(): array
    {
        return [
            self::LOCATION_RENDERED_PAGE => __('admin.maintenance_form_locations.rendered_page'),
            self::LOCATION_RAW_HTML => __('admin.maintenance_form_locations.raw_html'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function positions(): array
    {
        return [
            self::POSITION_BEFORE_CONTENT,
            self::POSITION_AFTER_CONTENT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function positionOptions(): array
    {
        return [
            self::POSITION_BEFORE_CONTENT => __('admin.maintenance_form_positions.before_content'),
            self::POSITION_AFTER_CONTENT => __('admin.maintenance_form_positions.after_content'),
        ];
    }
}
