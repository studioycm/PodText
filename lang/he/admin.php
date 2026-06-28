<?php

return [
    'navigation' => [
        'content' => 'תוכן',
    ],
    'resources' => [
        'author' => [
            'singular' => 'מחבר',
            'plural' => 'מחברים',
            'navigation' => 'מחברים',
        ],
        'content_group' => [
            'singular' => 'קבוצת תוכן',
            'plural' => 'קבוצות תוכן',
            'navigation' => 'קבוצות תוכן',
        ],
        'content_item' => [
            'singular' => 'פריט תוכן',
            'plural' => 'פריטי תוכן',
            'navigation' => 'פריטי תוכן',
        ],
    ],
    'sections' => [
        'identity' => 'זהות',
        'content' => 'תוכן',
        'publication' => 'פרסום',
        'transcript' => 'תמלול',
        'type_labels' => 'תוויות תצוגה',
    ],
    'fields' => [
        'author_name' => 'שם',
        'authors' => 'מחברים',
        'bio_markdown' => 'ביוגרפיה',
        'content_group' => 'קבוצת תוכן',
        'cover_path' => 'תמונת שער',
        'created_at' => 'נוצר',
        'default_item_type_label_plural' => 'תווית רבים לפריטים',
        'default_item_type_label_singular' => 'תווית יחיד לפריטים',
        'description_markdown' => 'תיאור',
        'duration_seconds' => 'משך בשניות',
        'effective_type_label' => 'תווית סוג',
        'embed_url' => 'כתובת הטמעה',
        'group_type_label_plural' => 'תווית רבים לקבוצה',
        'group_type_label_singular' => 'תווית יחיד לקבוצה',
        'media_url' => 'כתובת מדיה',
        'original_language_code' => 'שפת מקור',
        'original_published_at' => 'תאריך פרסום מקורי',
        'published_at' => 'תאריך פרסום',
        'reference_key' => 'מפתח ייחוס',
        'slug' => 'מזהה כתובת',
        'status' => 'סטטוס',
        'title' => 'כותרת',
        'transcript_markdown' => 'תמלול',
        'type_label_singular_override' => 'תווית יחיד חלופית לפריט',
        'updated_at' => 'עודכן',
    ],
    'helpers' => [
        'default_item_type_label_plural' => 'ברירת מחדל: Episodes.',
        'default_item_type_label_singular' => 'ברירת מחדל: Episode.',
        'embed_url' => 'HTTPS בלבד. יש להשתמש במארח הטמעה מאושר.',
        'group_type_label_plural' => 'ברירת מחדל: Podcasts.',
        'group_type_label_singular' => 'ברירת מחדל: Podcast.',
        'type_label_singular_override' => 'להשאיר ריק כדי לרשת את ברירת המחדל מהקבוצה.',
    ],
    'locales' => [
        'en' => 'אנגלית',
        'he' => 'עברית',
    ],
    'publication_status' => [
        'draft' => 'טיוטה',
        'published' => 'פורסם',
    ],
    'import' => [
        'columns' => [
            'author_reference_keys' => 'Author reference keys',
            'content_group_reference_key' => 'Content group reference key',
        ],
        'failures' => [
            'create_found_existing_reference_key' => 'Create-only import found an existing record with reference key :reference_key.',
            'duplicate_reference_key' => 'The reference key :reference_key appears more than once in this import chunk.',
            'unresolved_authors' => 'Could not resolve author reference keys: :reference_keys.',
            'unresolved_content_group' => 'Could not resolve content group reference key :reference_key.',
            'update_missing_reference_key' => 'Update-only import could not find a record with reference key :reference_key.',
            'update_requires_reference_key' => 'Update-only import rows must include a reference key.',
        ],
        'options' => [
            'blank_update_behavior' => 'Blank mapped fields on update',
            'blank_update_behaviors' => [
                'overwrite' => 'Overwrite with blank values where allowed',
                'preserve' => 'Preserve existing values',
            ],
            'mode' => 'Import mode',
            'modes' => [
                'create' => 'Create only',
                'update' => 'Update only',
                'upsert' => 'Create and update',
            ],
        ],
    ],
    'validation' => [
        'media_url_https' => 'כתובת המדיה חייבת להשתמש ב-HTTPS.',
        'embed_url_host' => 'מארח כתובת ההטמעה אינו מאושר.',
        'embed_url_https' => 'כתובת ההטמעה חייבת להשתמש ב-HTTPS.',
        'embed_url_no_html' => 'יש להדביק כתובת הטמעה, לא HTML.',
        'embed_url_url' => 'כתובת ההטמעה חייבת להיות כתובת תקינה.',
    ],
];
