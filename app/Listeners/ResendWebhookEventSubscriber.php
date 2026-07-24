<?php

namespace App\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Resend\Laravel\Events\EmailBounced;
use Resend\Laravel\Events\EmailComplained;
use Resend\Laravel\Events\EmailDelivered;
use Resend\Laravel\Events\EmailDeliveryDelayed;
use Resend\Laravel\Events\EmailFailed;
use Resend\Laravel\Events\EmailSent;
use Resend\Laravel\Events\EmailSuppressed;

final class ResendWebhookEventSubscriber
{
    /**
     * @var array<class-string, string>
     */
    private const array EVENT_TYPES = [
        EmailSent::class => 'email.sent',
        EmailDelivered::class => 'email.delivered',
        EmailDeliveryDelayed::class => 'email.delivery_delayed',
        EmailBounced::class => 'email.bounced',
        EmailComplained::class => 'email.complained',
        EmailFailed::class => 'email.failed',
        EmailSuppressed::class => 'email.suppressed',
    ];

    public function record(
        EmailSent|EmailDelivered|EmailDeliveryDelayed|EmailBounced|EmailComplained|EmailFailed|EmailSuppressed $event,
    ): void {
        Log::channel('resend_webhook')->info('Resend email delivery webhook received.', [
            'event_type' => self::EVENT_TYPES[$event::class],
            'svix_id' => $this->boundedString(request()->header('svix-id')),
            'email_id' => $this->boundedString(data_get($event->payload, 'data.email_id')),
            'event_created_at' => $this->boundedString(data_get($event->payload, 'created_at')),
        ]);
    }

    public function subscribe(Dispatcher $events): void
    {
        foreach (array_keys(self::EVENT_TYPES) as $eventClass) {
            $events->listen($eventClass, [self::class, 'record']);
        }
    }

    private function boundedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : Str::limit($value, 255, '');
    }
}
