<?php

/**
 * LintReport.php
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
 * The aggregated result of a {@see ReadinessLinter} run: an ordered list of
 * findings plus the predicates a CI gate needs.
 *
 * The gate `passes()` when there are no `ERROR`-severity findings; warnings do
 * not fail it. {@see exitCode()} maps that to a process exit status (0 = pass,
 * 1 = at least one error) so the linter can be wired straight into CI.
 */
final class LintReport
{
    /**
     * @param list<LintFinding> $findings
     */
    public function __construct(public readonly array $findings)
    {
    }

    /**
     * Whether the readiness gate passes: true when no ERROR-severity finding is
     * present (warnings are advisory and do not fail the gate).
     */
    public function passes(): bool
    {
        foreach ($this->findings as $finding) {
            if ($finding->severity === LintSeverity::ERROR) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether there are any findings at all (errors or warnings).
     */
    public function hasFindings(): bool
    {
        return $this->findings !== [];
    }

    /**
     * Only the ERROR-severity findings.
     *
     * @return list<LintFinding>
     */
    public function errors(): array
    {
        return \array_values(\array_filter(
            $this->findings,
            static fn (LintFinding $f): bool => $f->severity === LintSeverity::ERROR
        ));
    }

    /**
     * The CI-friendly process exit code: 0 when the gate passes, 1 otherwise.
     */
    public function exitCode(): int
    {
        return $this->passes() ? 0 : 1;
    }

    /**
     * The JSON-serialisable report: pass flag plus the list of findings.
     *
     * @return array{passes: bool, findings: list<array{code: string, severity: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'passes'   => $this->passes(),
            'findings' => \array_map(
                static fn (LintFinding $f): array => $f->toArray(),
                $this->findings
            ),
        ];
    }
}
