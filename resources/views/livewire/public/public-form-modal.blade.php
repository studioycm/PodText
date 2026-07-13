<div>
    @if($definition !== null)
        <div
            x-data="{ open: false }"
            x-on:open-public-form.window="if (! $event.detail?.formKey || $event.detail.formKey === @js($definition['key'])) open = true"
            data-test="public-form-modal"
            data-form-key="{{ $definition['key'] }}"
            data-display-mode="{{ $displayMode }}"
        >
            @if($showTrigger)
                <button
                    type="button"
                    x-on:click="open = true"
                    class="inline-flex items-center justify-center rounded-md border border-primary-200 bg-primary-50 px-4 py-2 text-sm font-medium text-primary-800 transition hover:bg-primary-100 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-100 dark:hover:bg-primary-900"
                    data-test="public-form-open"
                >
                    {{ $definition['name'] }}
                </button>
            @endif

            <div
                x-show="open"
                x-on:keydown.escape.window="open = false"
                @class([
                    'fixed inset-0 z-50 bg-black/40 p-4',
                    'flex items-center justify-center' => $displayMode === 'modal',
                    'flex justify-end' => $displayMode === 'slide_over',
                ])
                data-test="public-form-overlay"
            >
                <div
                    @class([
                        'w-full overflow-y-auto rounded-lg bg-white p-6 shadow-xl dark:bg-gray-900',
                        'max-w-xl' => $displayMode === 'modal',
                        'max-w-md min-h-full' => $displayMode === 'slide_over',
                    ])
                    role="dialog"
                    aria-modal="true"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 space-y-1">
                            <h2 class="text-xl font-semibold tracking-normal text-gray-950 dark:text-white">
                                {{ $definition['heading'] }}
                            </h2>

                            @if(filled($definition['description']))
                                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                                    {{ $definition['description'] }}
                                </p>
                            @endif
                        </div>

                        <button
                            type="button"
                            x-on:click="open = false"
                            class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                            aria-label="{{ __('public.actions.close') }}"
                            data-test="public-form-close"
                        >
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    @if($successMessage)
                        <div class="mt-4 rounded-md border border-success-200 bg-success-50 p-3 text-sm text-success-800 dark:border-success-800 dark:bg-success-950 dark:text-success-100" data-test="public-form-success">
                            {{ $successMessage }}
                        </div>
                    @endif

                    @if($errors->has('form'))
                        <div class="mt-4 rounded-md border border-danger-200 bg-danger-50 p-3 text-sm text-danger-800 dark:border-danger-800 dark:bg-danger-950 dark:text-danger-100" data-test="public-form-error">
                            {{ $errors->first('form') }}
                        </div>
                    @endif

                    <form wire:submit="submit" class="mt-5 space-y-4" data-test="public-form">
                        <div class="sr-only" aria-hidden="true">
                            <label>
                                {{ __('public.forms.honeypot') }}
                                <input type="text" tabindex="-1" autocomplete="off" wire:model="honeypot">
                            </label>
                        </div>

                        @foreach($fields as $field)
                            <div class="space-y-1" wire:key="public-form-field-{{ $field['key'] }}">
                                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                                    <span>
                                        {{ $field['label'] }}
                                        @if($field['required'])
                                            <span class="text-danger-600" aria-hidden="true">*</span>
                                        @endif
                                    </span>

                                    @if(in_array($field['type'], ['text', 'email', 'phone', 'url'], true))
                                        <input
                                            type="{{ $field['type'] === 'phone' ? 'tel' : $field['type'] }}"
                                            wire:model="data.{{ $field['key'] }}"
                                            placeholder="{{ $field['placeholder'] }}"
                                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                                            data-test="public-form-field-{{ $field['key'] }}"
                                        >
                                    @elseif($field['type'] === 'textarea')
                                        <textarea
                                            wire:model="data.{{ $field['key'] }}"
                                            placeholder="{{ $field['placeholder'] }}"
                                            rows="5"
                                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                                            data-test="public-form-field-{{ $field['key'] }}"
                                        ></textarea>
                                    @elseif($field['type'] === 'select')
                                        <select
                                            wire:model="data.{{ $field['key'] }}"
                                            class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                                            data-test="public-form-field-{{ $field['key'] }}"
                                        >
                                            <option value="">{{ __('public.forms.choose_option') }}</option>
                                            @foreach($field['options'] as $option)
                                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($field['type'] === 'checkbox' && $field['options'] !== [])
                                        <span class="grid gap-2" data-test="public-form-field-{{ $field['key'] }}">
                                            @foreach($field['options'] as $option)
                                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                                    <input
                                                        type="checkbox"
                                                        value="{{ $option['value'] }}"
                                                        wire:model="data.{{ $field['key'] }}"
                                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                                                    >
                                                    <span>{{ $option['label'] }}</span>
                                                </label>
                                            @endforeach
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                wire:model="data.{{ $field['key'] }}"
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                                                data-test="public-form-field-{{ $field['key'] }}"
                                            >
                                            <span>{{ $field['placeholder'] ?: $field['label'] }}</span>
                                        </span>
                                    @endif
                                </label>

                                @if(filled($field['help_text']))
                                    <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                                        {{ $field['help_text'] }}
                                    </p>
                                @endif

                                @error('data.'.$field['key'])
                                    <p class="text-xs text-danger-600 dark:text-danger-400" data-test="public-form-field-error-{{ $field['key'] }}">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        @endforeach

                        @if($verificationRequired)
                            <div class="rounded-md border border-primary-200 bg-primary-50 p-3 text-sm text-primary-900 dark:border-primary-800 dark:bg-primary-950 dark:text-primary-100" data-test="public-form-verification">
                                <div class="space-y-2">
                                    <p class="font-medium">{{ __('public.forms.verification.title') }}</p>

                                    <div class="flex flex-wrap items-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="sendEmailVerificationCode"
                                            wire:loading.attr="disabled"
                                            wire:target="sendEmailVerificationCode"
                                            class="inline-flex items-center justify-center rounded-md border border-primary-700 bg-primary-700 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                            data-test="public-form-send-code"
                                        >
                                            {{ __('public.forms.verification.send_code') }}
                                        </button>

                                        <label class="grid gap-1">
                                            <span>{{ __('public.forms.verification.code_label') }}</span>
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                autocomplete="one-time-code"
                                                wire:model="emailVerificationCode"
                                                class="w-36 rounded-md border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950"
                                                data-test="public-form-code"
                                            >
                                        </label>

                                        <button
                                            type="button"
                                            wire:click="verifyEmailCode"
                                            wire:loading.attr="disabled"
                                            wire:target="verifyEmailCode"
                                            class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                            data-test="public-form-verify-code"
                                        >
                                            {{ __('public.forms.verification.verify_code') }}
                                        </button>
                                    </div>

                                    @if($emailVerificationStatus)
                                        <p data-test="public-form-verification-status">{{ $emailVerificationStatus }}</p>
                                    @endif

                                    @error('verification')
                                        <p class="text-danger-700 dark:text-danger-300" data-test="public-form-verification-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @endif

                        <div class="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                x-on:click="open = false"
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                            >
                                {{ __('public.actions.close') }}
                            </button>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-md border border-primary-700 bg-primary-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                wire:loading.attr="disabled"
                                @disabled($verificationRequired && ! $emailVerificationVerified)
                                data-test="public-form-submit"
                            >
                                {{ $definition['submit_label'] }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
