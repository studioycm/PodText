<?php

return [
    'otp' => [
        'expires_minutes' => (int) env('FORMS_OTP_EXPIRES_MINUTES', 5),
        'max_attempts' => (int) env('FORMS_OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('FORMS_OTP_RESEND_COOLDOWN_SECONDS', 60),
    ],
];
