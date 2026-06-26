<?php

return [
    'navigation' => [
        'content' => 'Content',
    ],
    'resources' => [
        'author' => [
            'singular' => 'Author',
            'plural' => 'Authors',
            'navigation' => 'Authors',
        ],
        'content_group' => [
            'singular' => 'Content Group',
            'plural' => 'Content Groups',
            'navigation' => 'Content Groups',
        ],
        'content_item' => [
            'singular' => 'Content Item',
            'plural' => 'Content Items',
            'navigation' => 'Content Items',
        ],
    ],
    'sections' => [
        'identity' => 'Identity',
        'content' => 'Content',
        'publication' => 'Publication',
        'transcript' => 'Transcript',
        'type_labels' => 'Display labels',
    ],
    'fields' => [
        'author_name' => 'Name',
        'authors' => 'Authors',
        'bio_markdown' => 'Biography',
        'content_group' => 'Content group',
        'cover_path' => 'Cover',
        'created_at' => 'Created',
        'default_item_type_label_plural' => 'Default item plural label',
        'default_item_type_label_singular' => 'Default item singular label',
        'description_markdown' => 'Description',
        'duration_seconds' => 'Duration seconds',
        'effective_type_label' => 'Type label',
        'embed_url' => 'Embed URL',
        'group_type_label_plural' => 'Group plural label',
        'group_type_label_singular' => 'Group singular label',
        'media_url' => 'Media URL',
        'original_language_code' => 'Original language',
        'original_published_at' => 'Original publication date',
        'published_at' => 'Publication date',
        'reference_key' => 'Reference key',
        'slug' => 'Slug',
        'status' => 'Status',
        'title' => 'Title',
        'transcript_markdown' => 'Transcript',
        'type_label_singular_override' => 'Item singular label override',
        'updated_at' => 'Updated',
    ],
    'helpers' => [
        'default_item_type_label_plural' => 'Default: Episodes.',
        'default_item_type_label_singular' => 'Default: Episode.',
        'embed_url' => 'HTTPS only. Use an approved embed host.',
        'group_type_label_plural' => 'Default: Podcasts.',
        'group_type_label_singular' => 'Default: Podcast.',
        'type_label_singular_override' => 'Leave blank to inherit the group default.',
    ],
    'locales' => [
        'en' => 'English',
        'he' => 'Hebrew',
    ],
    'publication_status' => [
        'draft' => 'Draft',
        'published' => 'Published',
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
        'embed_url_host' => 'The embed URL host is not approved.',
        'embed_url_https' => 'The embed URL must use HTTPS.',
        'embed_url_no_html' => 'Paste an embed URL, not HTML.',
        'embed_url_url' => 'The embed URL must be a valid URL.',
    ],
];
