<?php

declare(strict_types=1);

namespace Tests\Router\Unit\Matching;

use InitPHP\Router\Matching\PatternRegistry;
use PHPUnit\Framework\TestCase;

final class PatternRegistryTest extends TestCase
{
    public function testCompiledExposesDefaultPatternsAsSearchReplacePairs(): void
    {
        $compiled = (new PatternRegistry())->compiled();

        $this->assertSame(array_keys($compiled), ['keys', 'values']);
        $this->assertSameSize($compiled['keys'], $compiled['values']);
        $this->assertContains('#:id[0-9]?#', $compiled['keys']);
        $this->assertContains('(\d+)', $compiled['values']);
        // The trailing catch-all for any remaining "{name}" placeholder.
        $this->assertSame('#{[A-Za-z0-9]+}#', end($compiled['keys']));
        $this->assertSame('([\w\-_]+)', end($compiled['values']));
    }

    public function testColonIdPatternCompilesIntoADigitOnlyRegex(): void
    {
        $compiled = (new PatternRegistry())->compiled();

        // ":id" enforces digits; the brace form "{id}" is intentionally caught
        // first by the generic "{[^/]+}" rule and matches any non-slash segment.
        $regex = (string) preg_replace($compiled['keys'], $compiled['values'], '/user/:id');
        $this->assertSame(1, preg_match('#^' . $regex . '$#', '/user/42', $m));
        $this->assertSame('42', $m[1]);
        $this->assertSame(0, preg_match('#^' . $regex . '$#', '/user/abc'));
    }

    public function testBracePlaceholderMatchesAnyNonSlashSegment(): void
    {
        $compiled = (new PatternRegistry())->compiled();

        $regex = (string) preg_replace($compiled['keys'], $compiled['values'], '/user/{id}');
        $this->assertSame(1, preg_match('#^' . $regex . '$#', '/user/anything-123', $m));
        $this->assertSame('anything-123', $m[1]);
        $this->assertSame(0, preg_match('#^' . $regex . '$#', '/user/a/b'));
    }

    public function testAddWrapsBarePatternInACapturingGroup(): void
    {
        $registry = new PatternRegistry();
        $registry->add('year', '[0-9]{4}');

        $compiled = $registry->compiled();
        $index = array_search('#:year#', $compiled['keys'], true);

        $this->assertNotFalse($index);
        $this->assertSame('([0-9]{4})', $compiled['values'][$index]);
        $this->assertContains('#{year}#', $compiled['keys']);
    }

    public function testAddNormalisesDecoratedKeysAndKeepsExistingGroups(): void
    {
        $registry = new PatternRegistry();
        $registry->add(':slug', '([a-z]+)');

        $compiled = $registry->compiled();
        $index = array_search('#:slug#', $compiled['keys'], true);

        $this->assertNotFalse($index);
        $this->assertSame('([a-z]+)', $compiled['values'][$index]);
    }
}
