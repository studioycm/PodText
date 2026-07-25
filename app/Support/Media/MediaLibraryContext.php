<?php

namespace App\Support\Media;

use App\Enums\MediaDiagnosticReason;
use App\Enums\MediaLibraryTask;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use JsonException;

class MediaLibraryContext
{
    public const VERSION = 1;

    public const MAX_PAGE = 10_000;

    public const MAX_SEARCH_LENGTH = 200;

    /** @var array<int, string> */
    private const SORTS = [
        'created_at:asc',
        'created_at:desc',
    ];

    public function __construct(
        private readonly CuratorImageUploadPolicy $uploadPolicy,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|string|null>
     */
    public function capture(
        mixed $task,
        array $filters,
        mixed $search,
        mixed $sort,
        mixed $page,
        int $focus,
    ): array {
        $mime = data_get($filters, 'type.value');
        $reason = data_get($filters, 'reason.value');

        return [
            'v' => self::VERSION,
            'task' => $this->validTask($task) ?? MediaLibraryTask::All->value,
            'mime' => $this->validMime($mime),
            'reason' => $this->validReason($reason),
            'search' => $this->validSearch($search),
            'sort' => $this->validSort($sort),
            'page' => $this->validPage($page) ?? 1,
            'focus' => $focus,
        ];
    }

    public function normalizeFocus(mixed $focus): ?int
    {
        if (is_int($focus)) {
            return $focus > 0 ? $focus : null;
        }

        if (
            ! is_string($focus)
            || preg_match('/^[1-9][0-9]*$/', $focus) !== 1
        ) {
            return null;
        }

        $normalized = filter_var($focus, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return is_int($normalized) ? $normalized : null;
    }

    /**
     * @param  array<string, int|string|null>  $state
     * @return array{from: array<string, int|string|null>}
     */
    public function editParameters(array $state): array
    {
        return ['from' => collect($state)
            ->map(fn (int|string|null $value): int|string => $value ?? '')
            ->all()];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function fromInput(mixed $input, int $actualFocus): array
    {
        $state = $this->validatedInput($input);

        if ($state === null) {
            return $this->fallback($actualFocus);
        }

        $state['focus'] = $actualFocus;

        return $state;
    }

    /**
     * @param  array<string, int|string|null>  $state
     */
    public function continuationToken(array $state): string
    {
        $state = $this->validatedInput($state);

        if ($state === null) {
            throw new InvalidArgumentException('A Media Library continuation requires normalized context.');
        }

        return Crypt::encryptString(json_encode($state, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, int|string|null>
     */
    public function fromContinuationToken(mixed $token, int $fallbackFocus): array
    {
        if (! is_string($token) || blank($token)) {
            return $this->fallback($fallbackFocus);
        }

        try {
            $input = json_decode(
                Crypt::decryptString($token),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (DecryptException|JsonException) {
            return $this->fallback($fallbackFocus);
        }

        return $this->validatedInput($input) ?? $this->fallback($fallbackFocus);
    }

    /**
     * @param  array<string, int|string|null>  $state
     * @return array{origin: string}
     */
    public function continuationParameters(array $state): array
    {
        return ['origin' => $this->continuationToken($state)];
    }

    /**
     * @param  array<string, int|string|null>  $state
     * @return array<string, mixed>
     */
    public function indexParameters(array $state): array
    {
        $parameters = [
            'tab' => $state['task'],
        ];
        $filters = [];

        if (is_string($state['mime']) && $state['mime'] !== '') {
            $filters['type'] = ['value' => $state['mime']];
        }

        if (is_string($state['reason']) && $state['reason'] !== '') {
            $filters['reason'] = ['value' => $state['reason']];
        }

        if ($filters !== []) {
            $parameters['filters'] = $filters;
        }

        if (is_string($state['search']) && $state['search'] !== '') {
            $parameters['search'] = $state['search'];
        }

        if (is_string($state['sort']) && $state['sort'] !== '') {
            $parameters['sort'] = $state['sort'];
        }

        $parameters['page'] = $state['page'];
        $parameters['focus'] = $state['focus'];

        return $parameters;
    }

    /** @param array<string, int|string|null> $state */
    public function fragment(array $state): string
    {
        return '#media-record-'.(int) $state['focus'];
    }

    /** @param array<string, mixed> $input */
    private function hasExactShape(array $input): bool
    {
        $expected = ['focus', 'mime', 'page', 'reason', 'search', 'sort', 'task', 'v'];
        $actual = array_keys($input);
        sort($actual);

        return $actual === $expected;
    }

    /**
     * @return array<string, int|string|null>|null
     */
    private function validatedInput(mixed $input): ?array
    {
        if (! is_array($input) || ! $this->hasExactShape($input)) {
            return null;
        }

        $task = $this->validTask($input['task']);
        $mime = $this->validNullableMime($input['mime']);
        $reason = $this->validNullableReason($input['reason']);
        $search = $this->validNullableSearch($input['search']);
        $sort = $this->validNullableSort($input['sort']);
        $page = $this->validPage($input['page']);

        if (
            ! in_array($input['v'], [self::VERSION, (string) self::VERSION], true)
            || ! $this->validPositiveInteger($input['focus'])
            || $task === null
            || $mime === false
            || $reason === false
            || $search === false
            || $sort === false
            || $page === null
        ) {
            return null;
        }

        return [
            'v' => self::VERSION,
            'task' => $task,
            'mime' => $mime,
            'reason' => $reason,
            'search' => $search,
            'sort' => $sort,
            'page' => $page,
            'focus' => (int) $input['focus'],
        ];
    }

    /** @return array<string, int|string|null> */
    private function fallback(int $actualFocus): array
    {
        return [
            'v' => self::VERSION,
            'task' => MediaLibraryTask::All->value,
            'mime' => null,
            'reason' => null,
            'search' => null,
            'sort' => null,
            'page' => 1,
            'focus' => $actualFocus,
        ];
    }

    private function validTask(mixed $value): ?string
    {
        return is_string($value) ? MediaLibraryTask::tryFrom($value)?->value : null;
    }

    private function validMime(mixed $value): ?string
    {
        return is_string($value) && in_array($value, $this->uploadPolicy->globalMimeTypes(), true)
            ? $value
            : null;
    }

    private function validNullableMime(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->validMime($value) ?? false;
    }

    private function validReason(mixed $value): ?string
    {
        return is_string($value) ? MediaDiagnosticReason::tryFrom($value)?->value : null;
    }

    private function validNullableReason(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->validReason($value) ?? false;
    }

    private function validSearch(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        $controlCharacterMatch = mb_check_encoding($value, 'UTF-8')
            ? preg_match('/[\p{Cc}\p{Cf}]/u', $value)
            : false;

        if (
            $value === ''
            || $controlCharacterMatch !== 0
            || mb_strlen($value) > self::MAX_SEARCH_LENGTH
        ) {
            return null;
        }

        return $value;
    }

    private function validNullableSearch(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        return $this->validSearch($value) ?? false;
    }

    private function validSort(mixed $value): ?string
    {
        return is_string($value) && in_array($value, self::SORTS, true)
            ? $value
            : null;
    }

    private function validNullableSort(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->validSort($value) ?? false;
    }

    private function validPage(mixed $value): ?int
    {
        if (! $this->validPositiveInteger($value)) {
            return null;
        }

        $page = (int) $value;

        return $page <= self::MAX_PAGE ? $page : null;
    }

    private function validPositiveInteger(mixed $value): bool
    {
        return is_int($value)
            ? $value > 0
            : is_string($value) && ctype_digit($value) && (int) $value > 0;
    }
}
