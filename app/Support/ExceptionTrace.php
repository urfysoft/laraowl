<?php

namespace App\Support;

final class ExceptionTrace
{
    /**
     * Normalize the client's JSON-encoded exception trace for dashboard views.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalize(array $payload): array
    {
        $trace = $payload['stack'] ?? $payload['trace'] ?? [];

        if (is_string($trace)) {
            $trace = json_decode($trace, true);
        }

        if (! is_array($trace)) {
            $trace = [];
        }

        $payload['stack'] = array_values(array_map(
            self::normalizeFrame(...),
            array_filter($trace, 'is_array'),
        ));

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $frame
     * @return array<string, mixed>
     */
    private static function normalizeFrame(array $frame): array
    {
        $file = is_string($frame['file'] ?? null) ? $frame['file'] : '';
        $line = is_numeric($frame['line'] ?? null) ? (int) $frame['line'] : null;

        if ($line === null && preg_match('/^(.*):(\d+)$/s', $file, $matches) === 1) {
            $file = $matches[1];
            $line = (int) $matches[2];
        }

        $source = is_string($frame['source'] ?? null) ? $frame['source'] : '';
        [$class, $type, $function] = self::parseSource($source);
        $preview = is_array($frame['preview'] ?? null)
            ? $frame['preview']
            : (is_array($frame['code'] ?? null) ? $frame['code'] : null);

        return array_merge($frame, [
            'file' => $file,
            'line' => $line,
            'class' => $frame['class'] ?? $class,
            'type' => $frame['type'] ?? $type,
            'function' => $frame['function'] ?? $function,
            'preview' => $preview,
            'snippet' => $frame['snippet'] ?? self::snippet($preview),
        ]);
    }

    /**
     * @return array{string, string, string}
     */
    private static function parseSource(string $source): array
    {
        $callable = preg_replace('/\(.*$/s', '', $source) ?? '';

        if (preg_match('/^(.*)(::|->)([^:>]+)$/s', $callable, $matches) === 1) {
            return [$matches[1], $matches[2], $matches[3]];
        }

        return ['', '', $callable];
    }

    /**
     * @param  array<array-key, mixed>|null  $preview
     */
    private static function snippet(?array $preview): ?string
    {
        if ($preview === null) {
            return null;
        }

        return implode("\n", array_map(
            static fn (mixed $line): string => is_scalar($line) || $line === null ? (string) $line : '',
            array_values($preview),
        ));
    }
}
