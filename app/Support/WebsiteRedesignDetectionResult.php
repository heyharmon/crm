<?php

namespace App\Support;

/**
 * Value object describing the outcome of a Wayback redesign detection run.
 *
 * @phpstan-type RedesignEvent array{
 *     before_timestamp: string,
 *     before_captured_at: ?\Carbon\Carbon,
 *     after_timestamp: string,
 *     after_captured_at: ?\Carbon\Carbon,
 *     nav_similarity: ?float,
 *     before_html_class_count: ?int,
 *     after_html_class_count: ?int,
 *     before_body_class_count: ?int,
 *     after_body_class_count: ?int,
 *     before_head_asset_count: ?int,
 *     after_head_asset_count: ?int,
 *     before_html_classes: array<int, string>,
 *     after_html_classes: array<int, string>,
 *     before_body_classes: array<int, string>,
 *     after_body_classes: array<int, string>,
 *     before_head_assets: array<int, string>,
 *     after_head_assets: array<int, string>,
 *     before_head_html: ?string,
 *     after_head_html: ?string
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
