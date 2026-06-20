<?php

/**
 * HealthReport.php
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
 * The aggregated result of running every registered readiness check.
 *
 * The report is healthy only when all checks passed; the corresponding HTTP
 * status is 200 when healthy and 503 (Service Unavailable) otherwise — the
 * conventional readiness contract for load balancers and orchestrators.
 */
final class HealthReport
{
    /**
     * @param bool               $healthy Whether every check passed.
     * @param list<CheckResult>  $results The individual check outcomes, in registration order.
     */
    public function __construct(
        public readonly bool $healthy,
        public readonly array $results
    ) {
    }

    /**
     * The HTTP status code that represents this report: 200 when healthy,
     * 503 (Service Unavailable) when any check failed.
     */
    public function statusCode(): int
    {
        return $this->healthy ? 200 : 503;
    }

    /**
     * The JSON-serialisable body: an overall "ok"/"fail" status plus a map of
     * check name => {status, detail?}.
     *
     * @return array{status: string, checks: array<string, array{status: string, detail?: string}>}
     */
    public function toArray(): array
    {
        $checks = [];
        foreach ($this->results as $result) {
            $checks[$result->name] = $result->toArray();
        }

        return [
            'status' => $this->healthy ? 'ok' : 'fail',
            'checks' => $checks,
        ];
    }
}
