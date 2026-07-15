@php
    $booleanValue = static function (mixed $value): bool {
        return in_array($value, [true, 1, '1', 'on', 'true'], true);
    };
@endphp

<style>
    .podtext-maintenance-form {
        margin-block: 1.5rem;
        padding: 1rem;
        border: 1px solid var(--maintenance-border, #d7d7ce);
        border-radius: 0.5rem;
        text-align: start;
    }

    .podtext-maintenance-form__header {
        margin-block-end: 1rem;
    }

    .podtext-maintenance-form__title {
        margin: 0;
        font-size: 1.25rem;
        line-height: 1.4;
    }

    .podtext-maintenance-form__description,
    .podtext-maintenance-form__help {
        color: var(--maintenance-muted, #5f6c72);
    }

    .podtext-maintenance-form__description {
        margin-block: 0.35rem 0;
    }

    .podtext-maintenance-form__fields {
        display: grid;
        gap: 1rem;
    }

    .podtext-maintenance-form__field {
        display: grid;
        gap: 0.35rem;
    }

    .podtext-maintenance-form__input-action {
        display: flex;
        flex-direction: row;
        align-items: stretch;
        gap: 0.5rem;
    }

    .podtext-maintenance-form__input-action .podtext-maintenance-form__input {
        min-width: 0;
        flex: 1;
    }

    .podtext-maintenance-form__label {
        font-size: 0.95rem;
        font-weight: 600;
    }

    .podtext-maintenance-form__input,
    .podtext-maintenance-form__textarea,
    .podtext-maintenance-form__select {
        width: 100%;
        border: 1px solid var(--maintenance-border, #d7d7ce);
        border-radius: 0.375rem;
        background: var(--maintenance-panel, #ffffff);
        color: var(--maintenance-text, #1f2933);
        font: inherit;
        padding: 0.65rem 0.75rem;
    }

    .podtext-maintenance-form__textarea {
        min-height: 8rem;
        resize: vertical;
    }

    .podtext-maintenance-form__choices {
        display: grid;
        gap: 0.5rem;
    }

    .podtext-maintenance-form__choice {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .podtext-maintenance-form__required,
    .podtext-maintenance-form__error {
        color: #be123c;
    }

    .podtext-maintenance-form__alert {
        margin-block-end: 1rem;
        border-radius: 0.375rem;
        padding: 0.75rem;
    }

    .podtext-maintenance-form__alert--success {
        border: 1px solid #047857;
        background: rgb(4 120 87 / 10%);
    }

    .podtext-maintenance-form__alert--error {
        border: 1px solid #be123c;
        background: rgb(190 18 60 / 10%);
    }

    .podtext-maintenance-form__actions {
        margin-block-start: 1rem;
        display: flex;
        flex-wrap: wrap;
        justify-content: end;
        gap: 0.5rem;
    }

    .podtext-maintenance-form__button {
        border: 1px solid #b45309;
        border-radius: 0.375rem;
        background: #b45309;
        color: #ffffff;
        cursor: pointer;
        font: inherit;
        font-weight: 600;
        padding: 0.65rem 1rem;
    }

    .podtext-maintenance-form__button--secondary {
        border-color: var(--maintenance-border, #d7d7ce);
        background: var(--maintenance-panel, #ffffff);
        color: var(--maintenance-text, #1f2933);
    }

    .podtext-maintenance-form__honeypot {
        position: absolute;
        inset-inline-start: -9999px;
        width: 1px;
        height: 1px;
        overflow: hidden;
    }
</style>

<section class="podtext-maintenance-form" data-maintenance-form data-form-key="{{ $definition['key'] }}">
    <div class="podtext-maintenance-form__header">
        <h2 class="podtext-maintenance-form__title">{{ $definition['heading'] }}</h2>

        @if(filled($definition['description']))
            <p class="podtext-maintenance-form__description">{{ $definition['description'] }}</p>
        @endif
    </div>

    @if(filled($formSuccessMessage))
        <div class="podtext-maintenance-form__alert podtext-maintenance-form__alert--success" data-maintenance-form-success>
            {{ $formSuccessMessage }}
        </div>
    @endif

    @if($formErrors->has('form'))
        <div class="podtext-maintenance-form__alert podtext-maintenance-form__alert--error" data-maintenance-form-error>
            {{ $formErrors->first('form') }}
        </div>
    @endif

    @if($formErrors->has('verification'))
        <div class="podtext-maintenance-form__alert podtext-maintenance-form__alert--error" data-maintenance-form-verification-error>
            {{ $formErrors->first('verification') }}
        </div>
    @endif

    @if(filled($formVerificationMessage))
        <div class="podtext-maintenance-form__alert podtext-maintenance-form__alert--success" data-maintenance-form-verification-message>
            {{ $formVerificationMessage }}
        </div>
    @endif

    <form method="POST" action="{{ $actionUrl }}" data-maintenance-form-post>
        @csrf

        <input type="hidden" name="source_url" value="{{ $sourceUrl }}">
        <input type="hidden" name="form_key" value="{{ $definition['key'] }}">

        @if(filled($formVerificationToken))
            <input type="hidden" name="verification_token" value="{{ $formVerificationToken }}">
        @endif

        <div class="podtext-maintenance-form__honeypot" aria-hidden="true">
            <label>
                {{ __('public.forms.honeypot') }}
                <input type="text" name="maintenance_honeypot" tabindex="-1" autocomplete="off">
            </label>
        </div>

        <div class="podtext-maintenance-form__fields">
            @foreach($fields as $field)
                @php
                    $fieldKey = $field['key'];
                    $fieldValue = $formData[$fieldKey] ?? ($field['type'] === 'checkbox' && $field['options'] !== [] ? [] : '');
                @endphp

                <div class="podtext-maintenance-form__field">
                    <label class="podtext-maintenance-form__label" for="maintenance-form-{{ $fieldKey }}">
                        {{ $field['label'] }}
                        @if($field['required'])
                            <span class="podtext-maintenance-form__required" aria-hidden="true">*</span>
                        @endif
                    </label>

                    @if(in_array($field['type'], ['text', 'email', 'phone', 'url'], true))
                        @if($formVerificationRequired && $fieldKey === $formVerificationEmailFieldKey)
                            <div
                                class="podtext-maintenance-form__input-action"
                                data-maintenance-form-email-verification-group
                            >
                                <input
                                    id="maintenance-form-{{ $fieldKey }}"
                                    class="podtext-maintenance-form__input"
                                    type="email"
                                    name="data[{{ $fieldKey }}]"
                                    value="{{ is_scalar($fieldValue) ? $fieldValue : '' }}"
                                    placeholder="{{ $field['placeholder'] }}"
                                    data-maintenance-form-email
                                    @required($field['required'])
                                >

                                <button
                                    class="podtext-maintenance-form__button podtext-maintenance-form__button--secondary"
                                    type="submit"
                                    formaction="{{ $sendCodeActionUrl }}"
                                    data-maintenance-form-send-code
                                    data-suffix-position="inline-end"
                                >
                                    {{ __('public.forms.verification.send_code') }}
                                </button>
                            </div>
                        @else
                            <input
                                id="maintenance-form-{{ $fieldKey }}"
                                class="podtext-maintenance-form__input"
                                type="{{ $field['type'] === 'phone' ? 'tel' : $field['type'] }}"
                                name="data[{{ $fieldKey }}]"
                                value="{{ is_scalar($fieldValue) ? $fieldValue : '' }}"
                                placeholder="{{ $field['placeholder'] }}"
                                @required($field['required'])
                            >
                        @endif
                    @elseif($field['type'] === 'textarea')
                        <textarea
                            id="maintenance-form-{{ $fieldKey }}"
                            class="podtext-maintenance-form__textarea"
                            name="data[{{ $fieldKey }}]"
                            rows="5"
                            placeholder="{{ $field['placeholder'] }}"
                            @required($field['required'])
                        >{{ is_scalar($fieldValue) ? $fieldValue : '' }}</textarea>
                    @elseif($field['type'] === 'select')
                        <select
                            id="maintenance-form-{{ $fieldKey }}"
                            class="podtext-maintenance-form__select"
                            name="data[{{ $fieldKey }}]"
                            @required($field['required'])
                        >
                            <option value="">{{ __('public.forms.choose_option') }}</option>
                            @foreach($field['options'] as $option)
                                <option value="{{ $option['value'] }}" @selected($fieldValue === $option['value'])>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    @elseif($field['type'] === 'checkbox' && $field['options'] !== [])
                        <div class="podtext-maintenance-form__choices" id="maintenance-form-{{ $fieldKey }}">
                            @foreach($field['options'] as $option)
                                <label class="podtext-maintenance-form__choice">
                                    <input
                                        type="checkbox"
                                        name="data[{{ $fieldKey }}][]"
                                        value="{{ $option['value'] }}"
                                        @checked(in_array($option['value'], (array) $fieldValue, true))
                                    >
                                    <span>{{ $option['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <label class="podtext-maintenance-form__choice">
                            <input
                                id="maintenance-form-{{ $fieldKey }}"
                                type="checkbox"
                                name="data[{{ $fieldKey }}]"
                                value="1"
                                @checked($booleanValue($fieldValue))
                                @required($field['required'])
                            >
                            <span>{{ $field['placeholder'] ?: $field['label'] }}</span>
                        </label>
                    @endif

                    @if(filled($field['help_text']))
                        <div class="podtext-maintenance-form__help">{{ $field['help_text'] }}</div>
                    @endif

                    @if($formErrors->has($fieldKey))
                        <div class="podtext-maintenance-form__error" data-maintenance-form-field-error="{{ $fieldKey }}">
                            {{ $formErrors->first($fieldKey) }}
                        </div>
                    @endif

                    @if($formVerificationRequired && $fieldKey === $formVerificationEmailFieldKey)
                        <div class="podtext-maintenance-form__field" data-maintenance-form-verification>
                            <label class="podtext-maintenance-form__label" for="maintenance-form-verification-code">
                                {{ __('public.forms.verification.code_label') }}
                            </label>

                            <input
                                id="maintenance-form-verification-code"
                                class="podtext-maintenance-form__input"
                                type="text"
                                name="verification_code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                value="{{ old('verification_code') }}"
                                placeholder="{{ __('public.forms.verification.code_placeholder') }}"
                                data-maintenance-form-code
                            >

                            <div class="podtext-maintenance-form__help">
                                {{ __('public.forms.verification.maintenance_help') }}
                            </div>

                            <div class="podtext-maintenance-form__help" data-maintenance-form-code-expiry-hint>
                                {{ trans_choice('public.forms.verification.expires_hint', $formVerificationExpiresAfterMinutes, ['count' => $formVerificationExpiresAfterMinutes]) }}
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="podtext-maintenance-form__actions">
            <button class="podtext-maintenance-form__button" type="submit" data-maintenance-form-submit>
                {{ $definition['submit_label'] }}
            </button>
        </div>
    </form>
</section>
