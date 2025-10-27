<?php

namespace App\Support;

/**
 * @phpstan-type RedesignEvent array{
 *     timestamp: string,
 *     digest: string|null,
 *     captured_at: \Carbon\Carbon,
 *     persistence_days: int,
 *     median_payload_bytes: ?int,
 *     payload_change_ratio: ?float
 * }
 */
class WebsiteRedesignDetectionResult
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_WAYBACK_FAILED = 'wayback_failed';
    public const STATUS_NO_WAYBACK_DATA = 'no_wayback_data';
    public const STATUS_NO_MAJOR_EVENTS = 'no_major_events';

    /**
     * @param array<int, RedesignEvent> $events
     */
    public function __construct(
        public array $events,
        public string $status,
        public ?string $message = null
    ) {}

    /**
     * @param array<int, RedesignEvent> $events
     */
    public static function success(array $events): self
    {
        return new self($events, self::STATUS_SUCCESS);
    }

    public static function waybackFailure(string $message): self
    {
        return new self([], self::STATUS_WAYBACK_FAILED, $message);
    }

    public static function noWaybackData(?string $message = null): self
    {
        return new self([], self::STATUS_NO_WAYBACK_DATA, $message);
    }

    public static function noMajorEvents(?string $message = null): self
    {
        return new self([], self::STATUS_NO_MAJOR_EVENTS, $message);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function hasEvents(): bool
    {
        return !empty($this->events);
    }
}
