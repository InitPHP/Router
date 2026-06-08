<?php

/**
 * PatternRegistry.php
 *
 * This file is part of Router.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Router\Matching;

/**
 * Holds the named route-parameter patterns and compiles them into the
 * preg search/replace pairs used to turn a route path into a regular
 * expression.
 */
final class PatternRegistry
{
    /** @var array<string, string> */
    private array $patterns = [
        '{[^/]+}'        => '([^/]+)',
        ':any[0-9]?'     => '([^/]+)',
        '{any[0-9]?}'    => '([^/]+)',
        ':id[0-9]?'      => '(\d+)',
        '{id[0-9]?}'     => '(\d+)',
        ':int[0-9]?'     => '(\d+)',
        '{int[0-9]?}'    => '(\d+)',
        ':number[0-9]?'  => '([+-]?([0-9]*[.])?[0-9]+)',
        '{number[0-9]?}' => '([+-]?([0-9]*[.])?[0-9]+)',
        ':float[0-9]?'   => '([+-]?([0-9]*[.])?[0-9]+)',
        '{float[0-9]?}'  => '([+-]?([0-9]*[.])?[0-9]+)',
        ':bool[0-9]?'    => '(true|false|1|0)',
        '{bool[0-9]?}'   => '(true|false|1|0)',
        ':string[0-9]?'  => '([\w\-_]+)',
        '{string[0-9]?}' => '([\w\-_]+)',
        ':slug[0-9]?'    => '([\w\-_]+)',
        '{slug[0-9]?}'   => '([\w\-_]+)',
        ':uuid[0-9]?'    => '([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})',
        '{uuid[0-9]?}'   => '([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})',
        ':date[0-9]?'    => '([0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]))',
        '{date[0-9]?}'   => '([0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]))',
        ':locale'        => '([A-Za-z]{2}|[A-Za-z]{2}[\_\-]{1}[A-Za-z]{2})',
        '{locale}'       => '([A-Za-z]{2}|[A-Za-z]{2}[\_\-]{1}[A-Za-z]{2})',
        ':everything'    => '(.*)',
        '{everything}'   => '(.*)',
    ];

    /**
     * Registers (or overrides) a named pattern.
     *
     * The key is normalised (surrounding ':' / '{}' are stripped) and the
     * pattern is wrapped in a capturing group when it is not already one.
     */
    public function add(string $key, string $pattern): void
    {
        $key = \trim($key, ':{}');
        if (\substr($pattern, 0, 1) !== '(' && \substr($pattern, -1) !== ')') {
            $pattern = '(' . $pattern . ')';
        }
        $this->patterns[':' . $key] = $pattern;
        $this->patterns['{' . $key . '}'] = $pattern;
    }

    /**
     * Compiles the registered patterns into preg search/replace pairs.
     *
     * A trailing catch-all entry maps any remaining "{name}" placeholder to a
     * slug-like capturing group.
     *
     * @return array{keys: list<string>, values: list<string>}
     */
    public function compiled(): array
    {
        $res = ['keys' => [], 'values' => []];
        foreach ($this->patterns as $key => $value) {
            $res['keys'][] = '#' . $key . '#';
            $res['values'][] = $value;
        }
        $res['keys'][] = '#{[A-Za-z0-9]+}#';
        $res['values'][] = '([\w\-_]+)';

        return $res;
    }
}
