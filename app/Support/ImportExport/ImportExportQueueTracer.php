<?php

namespace App\Support\ImportExport;

use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class ImportExportQueueTracer
{
    private const QUEUE = 'imports-exports';

    /**
     * @var array<int, string>
     */
    private const JOB_PREFIXES = [
        'Filament\\Actions\\Exports\\',
        'Filament\\Actions\\Imports\\',
    ];

    public function register(): void
    {
        Event::listen(JobQueueing::class, fn (JobQueueing $event): null => $this->traceQueuedEvent('queueing', $event));
        Event::listen(JobQueued::class, fn (JobQueued $event): null => $this->traceQueuedEvent('queued', $event));

        Event::listen(JobProcessing::class, fn (JobProcessing $event): null => $this->traceWorkerEvent('processing', $event->connectionName, $event->job));
        Event::listen(JobProcessed::class, fn (JobProcessed $event): null => $this->traceWorkerEvent('processed', $event->connectionName, $event->job));
        Event::listen(JobReleasedAfterException::class, fn (JobReleasedAfterException $event): null => $this->traceWorkerEvent('released_after_exception', $event->connectionName, $event->job, [
            'backoff' => $event->backoff,
        ]));
        Event::listen(JobTimedOut::class, fn (JobTimedOut $event): null => $this->traceWorkerEvent('timed_out', $event->connectionName, $event->job));
        Event::listen(JobExceptionOccurred::class, fn (JobExceptionOccurred $event): null => $this->traceWorkerEvent('exception', $event->connectionName, $event->job, [
            'exception' => $this->exceptionContext($event->exception),
        ]));
        Event::listen(JobFailed::class, fn (JobFailed $event): null => $this->traceWorkerEvent('failed', $event->connectionName, $event->job, [
            'exception' => $this->exceptionContext($event->exception),
        ]));
    }

    private function traceQueuedEvent(string $eventName, JobQueueing|JobQueued $event): null
    {
        if (! $this->shouldTraceQueuedEvent($event)) {
            return null;
        }

        $payload = $this->decodePayload($event->payload);

        $this->log($eventName, [
            'connection' => $event->connectionName,
            'queue' => $event->queue,
            'job_id' => $event instanceof JobQueued ? $event->id : null,
            'job' => $this->jobName($payload, $event->job),
            'display_name' => $payload['displayName'] ?? null,
            'uuid' => $payload['uuid'] ?? null,
            'delay' => $event->delay,
        ]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function traceWorkerEvent(string $eventName, string $connectionName, QueueJob $job, array $context = []): null
    {
        $queue = method_exists($job, 'getQueue') ? $job->getQueue() : null;

        if ($queue !== self::QUEUE && filled($queue)) {
            return null;
        }

        $payload = $this->jobPayload($job);

        if ($queue !== self::QUEUE && ! $this->payloadMatchesTracePrefixes($payload)) {
            return null;
        }

        $this->log($eventName, [
            'connection' => $connectionName,
            'queue' => $queue,
            'job_id' => method_exists($job, 'getJobId') ? $job->getJobId() : null,
            'job' => $this->jobName($payload),
            'display_name' => $payload['displayName'] ?? null,
            'uuid' => $payload['uuid'] ?? null,
            'attempts' => method_exists($job, 'attempts') ? $job->attempts() : null,
        ] + $context);

        return null;
    }

    private function shouldTraceQueuedEvent(JobQueueing|JobQueued $event): bool
    {
        if ($event->queue === self::QUEUE) {
            return true;
        }

        if ($this->jobMatchesTracePrefixes($event->job)) {
            return true;
        }

        if (filled($event->queue)) {
            return false;
        }

        return $this->payloadMatchesTracePrefixes($this->decodePayload($event->payload));
    }

    private function jobMatchesTracePrefixes(mixed $job): bool
    {
        if (is_object($job)) {
            return $this->matchesTracePrefixes($job::class);
        }

        return is_string($job) && $this->matchesTracePrefixes($job);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadMatchesTracePrefixes(array $payload): bool
    {
        return $this->matchesTracePrefixes($this->jobName($payload));
    }

    private function matchesTracePrefixes(string $jobName): bool
    {
        foreach (self::JOB_PREFIXES as $prefix) {
            if (str_starts_with($jobName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jobName(array $payload, mixed $job = null): string
    {
        $commandName = data_get($payload, 'data.commandName');

        if (is_string($commandName) && filled($commandName)) {
            return $commandName;
        }

        $displayName = $payload['displayName'] ?? null;

        if (is_string($displayName) && filled($displayName)) {
            return $displayName;
        }

        if (is_object($job)) {
            return $job::class;
        }

        if (is_string($job) && filled($job)) {
            return $job;
        }

        return 'unknown';
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(QueueJob $job): array
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = $job->payload();

            return $payload;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $payload): array
    {
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    private function exceptionContext(Throwable $exception): array
    {
        return [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $eventName, array $context): void
    {
        Log::channel('import_export')->info("Import/export queue job {$eventName}", $context);
    }
}
