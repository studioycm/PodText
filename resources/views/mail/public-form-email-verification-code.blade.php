@php
    $isRtl = $locale === 'he';
@endphp

<x-mail::message>
<div dir="{{ $isRtl ? 'rtl' : 'ltr' }}" style="text-align: {{ $isRtl ? 'right' : 'left' }};">

# {{ __('public.forms.verification.mail.heading', ['site' => $siteName]) }}

{{ __('public.forms.verification.mail.intro', ['form' => $formName]) }}

<x-mail::panel>
<span style="font-size: 28px; font-weight: 700; letter-spacing: 4px;">{{ $code }}</span>
</x-mail::panel>

{{ trans_choice('public.forms.verification.mail.expires', $expiresAfterMinutes, ['count' => $expiresAfterMinutes]) }}

{{ __('public.forms.verification.mail.ignore') }}

{{ __('public.forms.verification.mail.thanks', ['site' => $siteName]) }}

</div>
</x-mail::message>
