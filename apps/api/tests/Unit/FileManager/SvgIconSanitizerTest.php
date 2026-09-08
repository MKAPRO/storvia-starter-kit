<?php

namespace Tests\Unit\FileManager;

use App\Services\FileManager\SvgIconSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SvgIconSanitizerTest extends TestCase
{
    public function test_safe_icon_is_reconstructed_into_canonical_markup(): void
    {
        $sanitized = (new SvgIconSanitizer)->sanitize(
            '<svg viewBox="0 0 24 24"><path stroke-width="2" stroke="currentColor" fill="none" d="M2 2L22 22"/></svg>',
        );

        $this->assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M2 2L22 22" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
            $sanitized,
        );
    }

    #[DataProvider('unsafeSvgProvider')]
    public function test_unsafe_or_unparsed_svg_is_rejected(string $source): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SvgIconSanitizer)->sanitize($source);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeSvgProvider(): iterable
    {
        yield 'script' => ['<svg><script>alert(1)</script></svg>'];
        yield 'event' => ['<svg onload="alert(1)"><path d="M0 0L1 1"/></svg>'];
        yield 'onclick' => ['<svg><path onclick="alert(1)" d="M0 0L1 1"/></svg>'];
        yield 'javascript' => ['<svg><path fill="javascript:alert(1)" d="M0 0L1 1"/></svg>'];
        yield 'data-url' => ['<svg><path fill="data:text/html,x" d="M0 0L1 1"/></svg>'];
        yield 'foreign-object' => ['<svg><foreignObject><div>x</div></foreignObject></svg>'];
        yield 'external-href' => ['<svg><path href="https://example.com/x" d="M0 0L1 1"/></svg>'];
        yield 'xlink-href' => ['<svg><path xlink:href="https://example.com/x" d="M0 0L1 1"/></svg>'];
        yield 'remote-image' => ['<svg><image href="https://example.com/x.png"/></svg>'];
        yield 'style-url' => ['<svg><path style="fill:url(https://example.com/x)" d="M0 0L1 1"/></svg>'];
        yield 'xml-declaration' => ['<?xml version="1.0"?><svg><path d="M0 0L1 1"/></svg>'];
        yield 'doctype' => ['<!DOCTYPE svg><svg><path d="M0 0L1 1"/></svg>'];
        yield 'malformed' => ['<svg><path d="M0 0L1 1"></svg>'];
        yield 'text-content' => ['<svg>hello</svg>'];
        yield 'oversized' => ['<svg>'.str_repeat(' ', SvgIconSanitizer::MAX_SOURCE_BYTES).'</svg>'];
    }
}
