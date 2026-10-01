<?php

namespace EduLazaro\Larasources;

use EduLazaro\Larasources\Enums\OriginStatus;
use Throwable;

/**
 * The outcome of writing a source to its origin.
 *
 * An origin may return one of these from `save()` instead of the plain
 * response array when it needs to say more than "here is the response", for
 * example that the service accepted the payload but has not published it yet.
 * Returning an array means `Saved`.
 */
final class OriginResult
{
    public function __construct(
        public readonly OriginStatus $status,
        public readonly array $data = [],
        public readonly ?string $message = null,
        public readonly ?Throwable $exception = null,
    ) {
    }

    /**
     * The origin took the data: it is either stored or being processed.
     */
    public function ok(): bool
    {
        return $this->status !== OriginStatus::Failed;
    }

    public function processing(): bool
    {
        return $this->status === OriginStatus::Processing;
    }

    public function failed(): bool
    {
        return $this->status === OriginStatus::Failed;
    }
}
