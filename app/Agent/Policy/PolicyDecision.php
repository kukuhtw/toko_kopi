<?php

declare(strict_types=1);

namespace App\Agent\Policy;

final class PolicyDecision
{
    public bool $allowed;
    public string $reason;
    public string $resolution;

    public function __construct(bool $allowed, string $reason = '', string $resolution = 'proceed')
    {
        $this->allowed = $allowed;
        $this->reason = $reason;
        $this->resolution = $resolution;
    }

    public static function allow(): self
    {
        return new self(true, '', 'proceed');
    }

    public static function deny(string $reason, string $resolution = 'ask_clarification'): self
    {
        return new self(false, $reason, $resolution);
    }
}
