@php
    use App\Models\SettingsBackupSnapshot;

    $snapshots = $backup->snapshots()
        ->orderBy('screen_key')
        ->orderBy('theme')
        ->orderBy('kind')
        ->orderBy('format')
        ->get();
    $screens = $snapshots->pluck('screen_key')->unique()->values();
    $themes = $snapshots->pluck('theme')->unique()->values();
    $selectedScreen = $screens->first();
    $selectedTheme = $themes->first();
    $doneSnapshots = $snapshots
        ->filter(fn (SettingsBackupSnapshot $snapshot): bool => $snapshot->status === SettingsBackupSnapshot::STATUS_DONE && filled($snapshot->path));
@endphp

<div
    class="space-y-5"
    data-test="settings-backup-snapshots-gallery"
    x-data="{
        screen: @js($selectedScreen),
        theme: @js($selectedTheme),
    }"
>
    @if($snapshots->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
            {{ __('admin.messages.settings_backup_no_snapshots') }}
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2" role="tablist" aria-label="{{ __('admin.fields.screen_key') }}">
                @foreach($screens as $screenKey)
                    <button
                        type="button"
                        class="rounded-md border px-3 py-1.5 text-sm font-medium transition"
                        x-bind:class="screen === @js($screenKey) ? 'border-primary-600 bg-primary-50 text-primary-800 dark:border-primary-400 dark:bg-primary-950 dark:text-primary-100' : 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'"
                        x-on:click="screen = @js($screenKey)"
                        data-test="settings-backup-snapshot-screen-tab"
                        data-screen-key="{{ $screenKey }}"
                    >
                        {{ __("admin.settings_backup_snapshot_screens.{$screenKey}") }}
                    </button>
                @endforeach
            </div>

            @if($doneSnapshots->isNotEmpty())
                <a
                    href="{{ route('admin.settings-backups.snapshots-zip', $backup) }}"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900"
                    data-test="settings-backup-snapshots-download-all"
                >
                    {{ __('admin.actions.download_all_snapshots') }}
                </a>
            @endif
        </div>

        <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('admin.fields.snapshot_theme') }}">
            @foreach($themes as $theme)
                <button
                    type="button"
                    class="rounded-md border px-3 py-1.5 text-sm font-medium transition"
                    x-bind:class="theme === @js($theme) ? 'border-gray-950 bg-gray-950 text-white dark:border-white dark:bg-white dark:text-gray-950' : 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'"
                    x-on:click="theme = @js($theme)"
                    data-test="settings-backup-snapshot-theme"
                    data-theme="{{ $theme }}"
                >
                    {{ __("admin.settings_backup_snapshot_themes.{$theme}") }}
                </button>
            @endforeach
        </div>

        <div class="space-y-4">
            @foreach($snapshots as $snapshot)
                <article
                    class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-950"
                    x-show="screen === @js($snapshot->screen_key) && theme === @js($snapshot->theme)"
                    data-test="settings-backup-snapshot-card"
                    data-screen-key="{{ $snapshot->screen_key }}"
                    data-theme="{{ $snapshot->theme }}"
                    data-kind="{{ $snapshot->kind }}"
                    data-format="{{ $snapshot->format }}"
                    data-status="{{ $snapshot->status }}"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ __("admin.settings_backup_snapshot_screens.{$snapshot->screen_key}") }}
                                </span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    {{ __("admin.settings_backup_snapshot_kinds.{$snapshot->kind}") }}
                                </span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    {{ __("admin.settings_backup_snapshot_formats.{$snapshot->format}") }}
                                </span>
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-success-100 text-success-800 dark:bg-success-950 dark:text-success-200' => $snapshot->status === SettingsBackupSnapshot::STATUS_DONE,
                                    'bg-warning-100 text-warning-800 dark:bg-warning-950 dark:text-warning-200' => $snapshot->status === SettingsBackupSnapshot::STATUS_PENDING,
                                    'bg-danger-100 text-danger-800 dark:bg-danger-950 dark:text-danger-200' => $snapshot->status === SettingsBackupSnapshot::STATUS_FAILED,
                                ])>
                                    {{ __("admin.settings_backup_snapshot_statuses.{$snapshot->status}") }}
                                </span>
                            </div>
                            <a
                                href="{{ $snapshot->resolved_url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 block truncate text-xs text-gray-500 underline decoration-gray-300 underline-offset-2 dark:text-gray-400"
                            >
                                {{ $snapshot->resolved_url }}
                            </a>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if($snapshot->status === SettingsBackupSnapshot::STATUS_DONE && filled($snapshot->path))
                                <a
                                    href="{{ $snapshot->fileUrl(download: true) }}"
                                    class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900"
                                    data-test="settings-backup-snapshot-download"
                                >
                                    {{ __('admin.actions.download') }}
                                </a>
                            @endif

                            @if($snapshot->status === SettingsBackupSnapshot::STATUS_FAILED)
                                <form method="POST" action="{{ route('admin.settings-backup-snapshots.retry', $snapshot) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="rounded-md border border-warning-500 px-3 py-1.5 text-xs font-medium text-warning-700 transition hover:bg-warning-50 dark:text-warning-200 dark:hover:bg-warning-950"
                                        data-test="settings-backup-snapshot-retry"
                                    >
                                        {{ __('admin.actions.retry_snapshot') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if($snapshot->status === SettingsBackupSnapshot::STATUS_FAILED && filled($snapshot->error))
                        <p class="mt-3 rounded-md bg-danger-50 p-3 text-xs text-danger-800 dark:bg-danger-950 dark:text-danger-200">
                            {{ $snapshot->error }}
                        </p>
                    @endif

                    @if($snapshot->status === SettingsBackupSnapshot::STATUS_DONE && $snapshot->isImage() && filled($snapshot->fileUrl()))
                        <div
                            class="mt-4 max-h-[70vh] overflow-auto rounded-md border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900"
                            data-test="settings-backup-snapshot-scroll-container"
                        >
                            <img
                                src="{{ $snapshot->fileUrl() }}"
                                alt="{{ __("admin.settings_backup_snapshot_screens.{$snapshot->screen_key}") }}"
                                class="w-full min-w-[640px] bg-white dark:bg-gray-950"
                            >
                        </div>
                    @elseif($snapshot->status === SettingsBackupSnapshot::STATUS_DONE && filled($snapshot->fileUrl()))
                        <a
                            href="{{ $snapshot->fileUrl() }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-4 inline-flex rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900"
                        >
                            {{ __('admin.actions.open_snapshot_file') }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>
