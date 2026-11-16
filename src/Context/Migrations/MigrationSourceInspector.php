<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Migrations;

use ParseError;

/**
 * Reads familiar migration syntax without including or executing the PHP file.
 *
 * Token inspection is intentionally narrow: this is source evidence, not a
 * model of the migration's actual execution or all possible database calls.
 */
final class MigrationSourceInspector
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(string $source): array
    {
        try {
            $tokens = $this->tokens($source);
        } catch (ParseError $e) {
            return $this->result([], false, 'The migration source is not valid PHP: '.$e->getMessage());
        }

        $up = $this->methodBody($tokens, 'up');

        if ($up === null) {
            return $this->result([], false, 'No named up() method was found; migration behavior could not be inspected.');
        }

        [$begin, $end] = $up;
        $indicators = [];

        for ($i = $begin; $i < $end; $i++) {
            $name = strtolower(ltrim($tokens[$i]['text'], '\\'));

            if (! in_array($name, ['schema', 'illuminate\\support\\facades\\schema', 'db', 'illuminate\\support\\facades\\db'], true)) {
                continue;
            }

            $separator = $this->next($tokens, $i + 1);

            if (($tokens[$separator]['text'] ?? null) !== '::') {
                continue;
            }

            $methodIndex = $this->next($tokens, $separator + 1);
            $method = strtolower($tokens[$methodIndex]['text'] ?? '');
            $open = $this->next($tokens, $methodIndex + 1);

            if (($tokens[$open]['text'] ?? null) !== '(') {
                continue;
            }

            $close = $this->matching($tokens, $open, '(', ')');

            if ($close === null) {
                continue;
            }

            $table = $this->literal($tokens, $open, $close);
            $line = $tokens[$i]['line'];
            $schema = str_ends_with($name, 'schema');

            if ($schema) {
                if (in_array($method, ['drop', 'dropifexists', 'dropalltables'], true)) {
                    $indicators[] = $this->indicator('source_table_drop', 'high', $line, $table, null,
                        'Schema table removal appears in up().', 'Check retained data, references and the rollout sequence.');
                } elseif ($method === 'rename') {
                    $indicators[] = $this->indicator('source_table_rename', 'review', $line, $table, null,
                        'Schema table rename appears in up().', 'Verify both application versions support the naming transition.');
                } elseif ($method === 'table') {
                    $callback = $this->callback($tokens, $open, $close);

                    if ($callback === null) {
                        $indicators[] = $this->indicator('unresolved_schema_callback', 'review', $line, $table, null,
                            'Schema::table callback could not be inspected.', 'Review the callback manually; no complete source inference is possible.');
                    } else {
                        [$start, $stop, $variable] = $callback;
                        array_push($indicators, ...$this->blueprint($tokens, $start, $stop, $variable, $table));
                    }
                } elseif (in_array($method, ['disableforeignkeyconstraints', 'withoutforeignkeyconstraints'], true)) {
                    $indicators[] = $this->indicator('source_constraint_checks', 'review', $line, null, null,
                        'Foreign-key constraint checks are altered.', 'Verify referential integrity and restoration of constraint enforcement.');
                }
            } elseif (in_array($method, ['statement', 'unprepared', 'affectingstatement', 'update', 'delete', 'insert'], true)) {
                $indicators[] = $this->indicator('source_raw_sql', 'review', $line, null, null,
                    'Raw SQL or database mutation appears in up().', 'Inspect the SQL, affected data and transactional behavior manually.');
            }

            // Do not jump past nested Schema calls: closures can contain them.
        }

        usort($indicators, static fn (array $a, array $b): int =>
            [$a['line'], $a['code'], $a['table'], $a['column']] <=> [$b['line'], $b['code'], $b['table'], $b['column']]);

        return $this->result($indicators, true, null);
    }

    /**
     * @param  list<array{text: string, id: int|null, line: int}>  $tokens
     * @return list<array<string, mixed>>
     */
    private function blueprint(array $tokens, int $begin, int $end, string $variable, ?string $table): array
    {
        $indicators = [];

        for ($i = $begin; $i < $end; $i++) {
            if ($tokens[$i]['id'] !== T_VARIABLE || $tokens[$i]['text'] !== $variable) {
                continue;
            }

            $operator = $this->next($tokens, $i + 1);

            if (($tokens[$operator]['text'] ?? null) !== '->') {
                continue;
            }

            $methodIndex = $this->next($tokens, $operator + 1);
            $method = strtolower($tokens[$methodIndex]['text'] ?? '');
            $open = $this->next($tokens, $methodIndex + 1);

            if (($tokens[$open]['text'] ?? null) !== '(') {
                continue;
            }

            $close = $this->matching($tokens, $open, '(', ')');

            if ($close === null) {
                continue;
            }

            $column = $this->literal($tokens, $open, $close);
            $line = $tokens[$i]['line'];

            if (str_starts_with($method, 'drop')) {
                $level = in_array($method, ['dropcolumn', 'droptimestamps', 'dropsoftdeletes', 'dropmorphs', 'dropremembertoken'], true)
                    ? 'high' : 'review';
                $indicators[] = $this->indicator('source_blueprint_'.$method, $level, $line, $table, $column,
                    'Blueprint '.$method.'() appears in up().', 'Review existing data, application references and driver behavior.');
            } elseif ($method === 'renamecolumn') {
                $indicators[] = $this->indicator('source_column_rename', 'review', $line, $table, $column,
                    'Blueprint column rename appears in up().', 'Check both application versions and referenced columns.');
            } elseif (in_array($method, ['unique', 'foreign', 'index', 'primary'], true)) {
                $indicators[] = $this->indicator('source_constraint_or_index', 'review', $line, $table, $column,
                    'Blueprint index or constraint operation appears in up().', 'Review duplicates, affected records and index build/validation costs.');
            } elseif ($this->hasChainedChange($tokens, $close + 1, $end)) {
                $indicators[] = $this->indicator('source_column_change', 'review', $line, $table, $column,
                    'Blueprint column change appears in up().', 'Verify retained modifiers, conversion and database-specific rewrite behavior.');
            }
        }

        return $indicators;
    }

    /**
     * @param  list<array{text: string, id: int|null, line: int}>  $tokens
     */
    private function hasChainedChange(array $tokens, int $start, int $end): bool
    {
        for ($i = $start; $i < $end; $i++) {
            if (in_array($tokens[$i]['text'], [';', '}', ','], true)) {
                return false;
            }

            if ($tokens[$i]['text'] === '->') {
                $next = $this->next($tokens, $i + 1);

                if (strtolower($tokens[$next]['text'] ?? '') === 'change') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array{text: string, id: int|null, line: int}>  $tokens
     * @return array{int, int, string}|null
     */
    private function callback(array $tokens, int $open, int $close): ?array
    {
        for ($i = $open + 1; $i < $close; $i++) {
            if ($tokens[$i]['id'] !== T_FUNCTION) {
                continue;
            }

            $params = $this->next($tokens, $i + 1);

            if (($tokens[$params]['text'] ?? null) !== '(') {
                continue;
            }

            $paramsEnd = $this->matching($tokens, $params, '(', ')');

            if ($paramsEnd === null) {
                return null;
            }

            $variable = null;

            for ($p = $params + 1; $p < $paramsEnd; $p++) {
                if ($tokens[$p]['id'] === T_VARIABLE) {
                    $variable = $tokens[$p]['text'];

                    break;
                }
            }

            if ($variable === null) {
                return null;
            }

            $body = $this->next($tokens, $paramsEnd + 1);

            if (($tokens[$body]['text'] ?? null) === ':') {
                while ($body < $close && $tokens[$body]['text'] !== '{') {
                    $body++;
                }
            }

            if (($tokens[$body]['text'] ?? null) !== '{') {
                return null;
            }

            $end = $this->matching($tokens, $body, '{', '}');

            return $end === null ? null : [$body + 1, $end, $variable];
        }

        return null;
    }

    /**
     * @param  list<array{text: string, id: int|null, line: int}>  $tokens
     * @return array{int, int}|null
     */
    private function methodBody(array $tokens, string $method): ?array
    {
        foreach ($tokens as $i => $token) {
            if ($token['id'] !== T_FUNCTION) {
                continue;
            }

            $name = $this->next($tokens, $i + 1);

            if (($tokens[$name]['text'] ?? null) === '&') {
                $name = $this->next($tokens, $name + 1);
            }

            if (strtolower($tokens[$name]['text'] ?? '') !== $method) {
                continue;
            }

            $open = $this->next($tokens, $name + 1);

            if (($tokens[$open]['text'] ?? null) !== '(') {
                continue;
            }

            $argumentsEnd = $this->matching($tokens, $open, '(', ')');

            if ($argumentsEnd === null) {
                continue;
            }

            $body = $this->next($tokens, $argumentsEnd + 1);

            while (isset($tokens[$body]) && ! in_array($tokens[$body]['text'], ['{', ';'], true)) {
                $body++;
            }

            if (($tokens[$body]['text'] ?? null) !== '{') {
                continue;
            }

            $end = $this->matching($tokens, $body, '{', '}');

            if ($end !== null) {
                return [$body + 1, $end];
            }
        }

        return null;
    }

    /**
     * @param  list<array{text: string, id: int|null, line: int}>  $tokens
     */
    private function literal(array $tokens, int $open, int $close): ?string
    {
        $first = $this->next($tokens, $open + 1);

        if ($first >= $close || $tokens[$first]['id'] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        $quote = $tokens[$first]['text'][0];
        $value = substr($tokens[$first]['text'], 1, -1);

        if ($quote === "'") {
            $value = str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
        } else {
            $value = stripcslashes($value);
        }

        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]{0,127}$/D', $value) === 1 ? $value : null;
    }

    /**
     * @param  list<array{text: string, id: int|null, line: int}>  $tokens
     */
    private function matching(array $tokens, int $open, string $left, string $right): ?int
    {
        $depth = 0;

        for ($i = $open, $count = count($tokens); $i < $count; $i++) {
            if ($tokens[$i]['text'] === $left) {
                $depth++;
            } elseif ($tokens[$i]['text'] === $right && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<array{text: string, id: int|null, line: int}>  $tokens
     */
    private function next(array $tokens, int $index): int
    {
        while (isset($tokens[$index]) && in_array($tokens[$index]['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $index++;
        }

        return $index;
    }

    /**
     * @return list<array{text: string, id: int|null, line: int}>
     */
    private function tokens(string $source): array
    {
        $items = [];
        $line = 1;

        foreach (token_get_all($source, TOKEN_PARSE) as $token) {
            $text = is_array($token) ? $token[1] : $token;
            $items[] = ['text' => $text, 'id' => is_array($token) ? $token[0] : null, 'line' => $line];
            $line += substr_count($text, "\n");
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function indicator(string $code, string $level, int $line, ?string $table, ?string $column, string $message, string $verify): array
    {
        return compact('code', 'level', 'line', 'table', 'column', 'message', 'verify');
    }

    /**
     * @param  list<array<string, mixed>>  $indicators
     * @return array<string, mixed>
     */
    private function result(array $indicators, bool $upFound, ?string $reason): array
    {
        return [
            'format' => 'laravel-auditor-migration-source',
            'version' => 1,
            'coverage' => 'recognized_php_source_patterns',
            'up_found' => $upFound,
            'indicators' => $indicators,
            'reason' => $reason,
            'limitations' => [
                'PHP is tokenized and never executed. Conditions, custom helpers, macros and dynamic expressions cannot be evaluated.',
                'Only recognized operations inside an up() method are inspected; rollback methods are not assessed.',
                'Source occurrences do not prove the operation executed or what changes reached the database.',
                'No indicators does not establish migration or deployment safety.',
            ],
        ];
    }
}
