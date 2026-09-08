<?php

namespace App\Services\FileManager;

use InvalidArgumentException;

final class SvgIconSanitizer
{
    public const MAX_SOURCE_BYTES = 16384;

    private const MAX_ELEMENTS = 64;

    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'svg',
        'g',
        'path',
        'circle',
        'ellipse',
        'rect',
        'line',
        'polyline',
        'polygon',
    ];

    /** @var array<string, list<string>> */
    private const TAG_ATTRIBUTES = [
        'svg' => ['xmlns', 'viewBox', 'width', 'height', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin'],
        'g' => ['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'opacity', 'transform'],
        'path' => ['d', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'opacity', 'fill-rule', 'clip-rule', 'transform'],
        'circle' => ['cx', 'cy', 'r', 'fill', 'stroke', 'stroke-width', 'opacity', 'transform'],
        'ellipse' => ['cx', 'cy', 'rx', 'ry', 'fill', 'stroke', 'stroke-width', 'opacity', 'transform'],
        'rect' => ['x', 'y', 'width', 'height', 'rx', 'ry', 'fill', 'stroke', 'stroke-width', 'opacity', 'transform'],
        'line' => ['x1', 'y1', 'x2', 'y2', 'stroke', 'stroke-width', 'stroke-linecap', 'opacity', 'transform'],
        'polyline' => ['points', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'opacity', 'transform'],
        'polygon' => ['points', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'opacity', 'transform'],
    ];

    /** @var list<string> */
    private const VOID_TAGS = [
        'path',
        'circle',
        'ellipse',
        'rect',
        'line',
        'polyline',
        'polygon',
    ];

    public function sanitize(?string $source): ?string
    {
        if ($source === null || trim($source) === '') {
            return null;
        }

        if (strlen($source) > self::MAX_SOURCE_BYTES) {
            throw new InvalidArgumentException('SVG icon exceeds the supported size.');
        }

        $source = trim($source);

        if (
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $source) === 1
            || str_contains($source, '<!')
            || str_contains($source, '<?')
            || preg_match('/(?:javascript|data|vbscript)\s*:/i', $source) === 1
        ) {
            throw new InvalidArgumentException('SVG icon contains forbidden markup.');
        }

        preg_match_all('/<[^>]+>|[^<]+/s', $source, $matches);
        $tokens = $matches[0] ?? [];

        if ($tokens === [] || implode('', $tokens) !== $source) {
            throw new InvalidArgumentException('SVG icon markup is malformed.');
        }

        $stack = [];
        $output = '';
        $elementCount = 0;
        $rootSeen = false;
        $rootClosed = false;

        foreach ($tokens as $token) {
            if (! str_starts_with($token, '<')) {
                if (trim($token) !== '') {
                    throw new InvalidArgumentException('SVG icon text content is not supported.');
                }

                continue;
            }

            if (preg_match('/\A<\/\s*([A-Za-z][A-Za-z0-9-]*)\s*>\z/', $token, $closing) === 1) {
                $tag = strtolower($closing[1]);

                if ($stack === [] || array_pop($stack) !== $tag) {
                    throw new InvalidArgumentException('SVG icon tags are not properly nested.');
                }

                if (in_array($tag, self::VOID_TAGS, true)) {
                    throw new InvalidArgumentException('SVG geometry elements must be self-closing.');
                }

                $output .= "</{$tag}>";

                if ($tag === 'svg') {
                    $rootClosed = true;
                }

                continue;
            }

            if (preg_match('/\A<\s*([A-Za-z][A-Za-z0-9-]*)(.*?)>\z/s', $token, $opening) !== 1) {
                throw new InvalidArgumentException('SVG icon tag is malformed.');
            }

            $tag = strtolower($opening[1]);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                throw new InvalidArgumentException('SVG icon contains an unsupported element.');
            }

            $selfClosing = preg_match('/\/\s*>\z/', $token) === 1;
            $rawAttributes = preg_replace('/\/\s*\z/', '', trim((string) $opening[2]));

            if (! is_string($rawAttributes)) {
                throw new InvalidArgumentException('SVG icon attributes are malformed.');
            }

            if (! $rootSeen) {
                if ($tag !== 'svg' || $selfClosing) {
                    throw new InvalidArgumentException('SVG icon must have a non-empty svg root.');
                }

                $rootSeen = true;
            } elseif ($rootClosed || $stack === []) {
                throw new InvalidArgumentException('SVG icon must contain exactly one root element.');
            }

            if (in_array($tag, self::VOID_TAGS, true) && ! $selfClosing) {
                throw new InvalidArgumentException('SVG geometry elements must be self-closing.');
            }

            if ($tag === 'svg' && $stack !== []) {
                throw new InvalidArgumentException('Nested svg elements are not supported.');
            }

            $elementCount++;

            if ($elementCount > self::MAX_ELEMENTS) {
                throw new InvalidArgumentException('SVG icon contains too many elements.');
            }

            $attributes = $this->parseAttributes($tag, $rawAttributes);

            if ($tag === 'svg' && ! array_key_exists('viewBox', $attributes)) {
                $attributes['viewBox'] = '0 0 24 24';
            }

            $output .= '<'.$tag;

            if ($tag === 'svg') {
                $output .= ' xmlns="http://www.w3.org/2000/svg"';
            }

            foreach ($attributes as $name => $value) {
                $output .= ' '.$name.'="'.htmlspecialchars(
                    $value,
                    ENT_QUOTES | ENT_XML1,
                    'UTF-8',
                ).'"';
            }

            if ($selfClosing) {
                $output .= '/>';
            } else {
                $output .= '>';
                $stack[] = $tag;
            }
        }

        if (! $rootSeen || ! $rootClosed || $stack !== []) {
            throw new InvalidArgumentException('SVG icon root is incomplete.');
        }

        return $output;
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $tag, string $rawAttributes): array
    {
        if ($rawAttributes === '') {
            return [];
        }

        $attributes = [];
        $seen = [];
        $offset = 0;
        $length = strlen($rawAttributes);

        while ($offset < $length) {
            if (preg_match('/\G\s+/A', $rawAttributes, $space, 0, $offset) === 1) {
                $offset += strlen($space[0]);
            }

            if ($offset >= $length) {
                break;
            }

            if (
                preg_match(
                    '/\G([A-Za-z][A-Za-z0-9-]*)\s*=\s*("[^"]*"|\'[^\']*\')/A',
                    $rawAttributes,
                    $match,
                    0,
                    $offset,
                ) !== 1
            ) {
                throw new InvalidArgumentException('SVG icon contains an invalid attribute.');
            }

            $name = $match[1];
            $lowerName = strtolower($name);
            $value = substr($match[2], 1, -1);
            $offset += strlen($match[0]);

            if (
                str_starts_with($lowerName, 'on')
                || str_contains($lowerName, ':')
                || str_contains($lowerName, 'href')
                || $lowerName === 'style'
                || ! in_array($name, self::TAG_ATTRIBUTES[$tag] ?? [], true)
                || isset($seen[$name])
            ) {
                throw new InvalidArgumentException('SVG icon contains a forbidden attribute.');
            }

            $seen[$name] = true;

            if ($name === 'xmlns') {
                if ($tag !== 'svg' || trim($value) !== 'http://www.w3.org/2000/svg') {
                    throw new InvalidArgumentException('SVG icon namespace is invalid.');
                }

                continue;
            }

            if (! $this->attributeValueIsSafe($name, $value)) {
                throw new InvalidArgumentException('SVG icon contains an unsafe attribute value.');
            }

            $attributes[$name] = trim($value);
        }

        ksort($attributes);

        return $attributes;
    }

    private function attributeValueIsSafe(string $name, string $value): bool
    {
        $value = trim($value);

        if ($value === '' || strlen($value) > 4096 || str_contains($value, '<') || str_contains($value, '>')) {
            return false;
        }

        return match ($name) {
            'viewBox' => preg_match('/\A-?(?:\d+(?:\.\d+)?|\.\d+)(?:[ ,]+-?(?:\d+(?:\.\d+)?|\.\d+)){3}\z/', $value) === 1,
            'd' => preg_match('/\A[MmZzLlHhVvCcSsQqTtAa0-9eE+.,\s-]+\z/', $value) === 1,
            'points' => preg_match('/\A[0-9eE+.,\s-]+\z/', $value) === 1,
            'transform' => preg_match('/\A(?:matrix|translate|scale|rotate|skewX|skewY|[0-9eE+.,()\s-])+\z/', $value) === 1,
            'fill', 'stroke' => preg_match('/\A(?:none|currentColor|#[0-9a-fA-F]{3,8})\z/', $value) === 1,
            'stroke-linecap' => in_array($value, ['butt', 'round', 'square'], true),
            'stroke-linejoin' => in_array($value, ['miter', 'round', 'bevel'], true),
            'fill-rule', 'clip-rule' => in_array($value, ['nonzero', 'evenodd'], true),
            'opacity' => preg_match('/\A(?:0(?:\.\d+)?|1(?:\.0+)?)\z/', $value) === 1,
            default => preg_match('/\A-?(?:\d+(?:\.\d+)?|\.\d+)(?:e[+-]?\d+)?\z/i', $value) === 1,
        };
    }
}
