<?php

namespace Rakib\ServerLens\Data;

final class CheckResult
{
    public function __construct(
        public readonly string  $status,
        public readonly string  $detail,
        public readonly ?float  $latencyMs = null,
    ) {}

    public static function healthy(string $detail, ?float $latencyMs = null): self
    {
        return new self('healthy', $detail, $latencyMs);
    }

    public static function warning(string $detail, ?float $latencyMs = null): self
    {
        return new self('warning', $detail, $latencyMs);
    }

    public static function critical(string $detail, ?float $latencyMs = null): self
    {
        return new self('critical', $detail, $latencyMs);
    }

    public static function inactive(string $detail): self
    {
        return new self('inactive', $detail, null);
    }

    public function toArray(): array
    {
        return [
            'status'     => $this->status,
            'detail'     => $this->detail,
            'latency_ms' => $this->latencyMs,
        ];
    }
}
