<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The shop's stylesheet closes every block it opens.
 *
 * On 2026-10-08 the banner moved out of the home page and into this sheet,
 * and two rules arrived with their last line missing — `.hero-dot` and
 * `.hero-dot:hover` each lost a property and its closing brace. A browser
 * does not complain: it treats everything after an unclosed block as nested
 * inside it, so every rule below those two lines silently stopped applying
 * and stayed that way for three days. The catalogue's magnifier turned into
 * a black square the size of the card.
 *
 * Nothing else in the suite can see that, because the pages still render.
 */
class StylesheetIsWholeTest extends TestCase
{
    /** @return array<int, array<int, string>> */
    public static function sheets(): array
    {
        return [
            ['css/site.css'],
            ['css/admin-phone.css'],
            ['css/polaroid.css'],
            ['css/color-picker.css'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sheets')]
    public function test_every_block_is_closed(string $sheet): void
    {
        $path = public_path($sheet);

        if (! is_file($path)) {
            $this->markTestSkipped($sheet . ' is not in this installation.');
        }

        [$depth, $line] = self::walk((string) file_get_contents($path));

        $this->assertSame(0, $depth, $depth > 0
            ? $sheet . ": {$depth} block(s) never closed — everything after line {$line} is dead"
            : $sheet . ': a closing brace too many, around line ' . $line);
    }

    /**
     * How deep the braces go, ignoring comments and quoted strings.
     *
     * @return array{0: int, 1: int} the depth at the end, and the last line
     *                               at which it was zero
     */
    private static function walk(string $css): array
    {
        $depth = 0;
        $line = 1;
        $lastZero = 1;
        $inComment = false;
        $quote = null;
        $length = strlen($css);

        for ($i = 0; $i < $length; $i++) {
            $c = $css[$i];
            $next = $i + 1 < $length ? $css[$i + 1] : '';

            if ($c === "\n") {
                $line++;
                if ($depth === 0 && ! $inComment && $quote === null) {
                    $lastZero = $line;
                }

                continue;
            }

            if ($inComment) {
                if ($c === '*' && $next === '/') {
                    $inComment = false;
                    $i++;
                }

                continue;
            }

            if ($quote !== null) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($c === '/' && $next === '*') {
                $inComment = true;
                $i++;

                continue;
            }

            if ($c === '"' || $c === "'") {
                $quote = $c;

                continue;
            }

            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
            }
        }

        return [$depth, $lastZero];
    }
}
