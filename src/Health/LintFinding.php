<?php

/**
 * LintFinding.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router\Health;

/**
 * A single structured finding produced by {@see ReadinessLinter}.
 *
 * Each finding carries a stable machine `code` (suitable for allow-listing in
 * CI), a {@see LintSeverity}, and a human-readable `message`. The codes are
 * exposed as constants so callers can match on them without string-typos.
 */
final class LintFinding
{
    /** No liveness ("/healthz"-style) endpoint is registered. */
    public const MISSING_LIVENESS = 'missing_liveness';

    /** No readiness ("/readyz"-style) endpoint is registered. */
    public const MISSING_READINESS = 'missing_readiness';

    /** A readiness endpoint exists but no readiness checks are registered. */
    public const READINESS_HAS_NO_CHECKS = 'readiness_has_no_checks';

    public function __construct(
        public readonly string $code,
        public readonly LintSeverity $severity,
        public readonly string $message
    ) {
    }

    /**
     * The JSON-serialisable shape of this finding.
     *
     * @return array{code: string, severity: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'code'     => $this->code,
            'severity' => $this->severity->value,
            'message'  => $this->message,
        ];
    }
}
