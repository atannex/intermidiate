<?php

declare(strict_types=1);

namespace Atannex\Intermidiate\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Support\Str;

/**
 * The formatting Presenters keep repeating, in one place:
 *
 *     Format::excerpt($post->body, 160);   // tags stripped, entities decoded, cut at a word
 *     Format::date($post->published_at);   // "7 Mar 2026", in the app's timezone and locale
 *     Format::date($date, 'long');         // named formats from config intermidiate.dates
 *     Format::relative($date);             // "3 hours ago", or the date once it's older than a week
 *     Format::date($date, 'time', 'Africa/Douala');   // in another timezone
 *     Format::count(1204, 'story');        // "1,204 stories"
 *     Format::initials('Ma Ngum');         // "MN"
 *     Format::readingTime($post->body);    // 4 (minutes)
 */
final class Format
{
    /** @var array<string, string> */
    private const DATES = [
        'short' => 'j M',
        'medium' => 'j M Y',
        'long' => 'j F Y',
        'full' => 'l j F Y',
        'month' => 'M Y',
        'time' => 'H:i',
        'datetime' => 'j M Y, H:i',
    ];

    /** Plain text from HTML, cut at a word boundary: "Rain in Fontem closed…". */
    public static function excerpt(?string $html, int $limit = 160, string $end = '…'): string
    {
        $text = self::text($html);

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');

        if ($space !== false && $space > $limit * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:!?-–—").$end;
    }

    /** HTML to one line of plain text. */
    public static function text(?string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br', '</p>', '</li>', '</h'], [' <br', ' </p>', ' </li>', ' </h'], (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::squish($text);
    }

    /**
     * "Ma Ngum" → "MN"; "ngum" → "N". The first and last words by default;
     * $edges false takes the first ones ("Ayuk Tabe Ndip" → "AT"), and
     * $lettersOnly skips words that don't start with a letter ("FC 2000 Lions" → "FL"), and
     * $hyphens false keeps hyphenated and dotted names whole ("Essoh-Attah Eagles" → "EE").
     */
    public static function initials(?string $name, int $letters = 2, bool $edges = true, bool $lettersOnly = false, bool $hyphens = true): string
    {
        $words = preg_split($hyphens ? '/[\s\-_.]+/u' : '/[\s_]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($lettersOnly) {
            $words = array_values(array_filter($words, fn (string $word) => preg_match('/^\p{L}/u', $word) === 1));
        }

        if ($edges && count($words) > $letters) {
            $words = [$words[0], ...array_slice($words, -($letters - 1))];
        }

        return implode('', array_map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)), array_slice($words, 0, $letters)));
    }

    /** Minutes to read, at least 1. */
    public static function readingTime(?string $html, int $wordsPerMinute = 200): int
    {
        $words = count(preg_split('/\s+/u', self::text($html), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return max(1, (int) ceil($words / max(1, $wordsPerMinute)));
    }

    /**
     * A date in the app's timezone and locale. $format is a name from config
     * intermidiate.dates (short, medium, long, full, month, time, datetime)
     * or a format string. $timezone shows it somewhere else (a newsroom
     * clock, an edition's zone); $translate false keeps month and day names
     * untranslated, like Carbon's format().
     */
    public static function date(DateTimeInterface|string|int|null $date, string $format = 'medium', ?string $timezone = null, bool $translate = true): ?string
    {
        $date = self::carbon($date, $timezone);

        if ($date === null) {
            return null;
        }

        return $translate ? $date->translatedFormat(self::pattern($format)) : $date->format(self::pattern($format));
    }

    /** ISO 8601, for <time datetime> and JSON-LD. */
    public static function iso(DateTimeInterface|string|int|null $date, ?string $timezone = null): ?string
    {
        return self::carbon($date, $timezone)?->toIso8601String();
    }

    /**
     * "3 hours ago" for recent dates, the formatted date after $days. With
     * $days null it's always relative ("2 months ago"); $short gives "3h ago".
     */
    public static function relative(DateTimeInterface|string|int|null $date, ?int $days = 7, string $format = 'medium', ?string $timezone = null, bool $short = false): ?string
    {
        $date = self::carbon($date, $timezone);

        if ($date === null) {
            return null;
        }

        if ($days === null || abs($date->diffInDays(CarbonImmutable::now(), true)) < $days) {
            return $date->diffForHumans(short: $short);
        }

        return $date->translatedFormat(self::pattern($format));
    }

    /** "1,234", or "1,234.5" with decimals. */
    public static function number(int|float $number, int $decimals = 0): string
    {
        return number_format($number, $decimals);
    }

    /**
     * "1 story", "1,204 stories". A translation for ":count stories" (or the
     * pipe form ":count story|:count stories") is used when there is one.
     * The count is grouped by thousands unless $group is false ("1204 stories").
     */
    public static function count(int|float $count, string $noun, bool $group = true): string
    {
        $plural = Str::plural($noun, (int) $count);
        $decimals = is_float($count) && floor($count) !== $count ? 1 : 0;
        $shown = $group ? self::number($count, $decimals) : (string) ($decimals ? round($count, 1) : (int) $count);
        $key = ':count '.$plural;
        $container = Container::getInstance();

        if ($container->bound('translator')) {
            $translator = $container->make('translator');
            $choice = ':count '.Str::singular($noun).'|:count '.Str::plural($noun);

            if (! $translator->has($choice) && $translator->has($key)) {
                return $translator->get($key, ['count' => $shown]);
            }

            // Like trans_choice(): the locale's plural rules pick the form even when
            // there's no translation ("0 photo" in French), so it's a drop-in for it.
            return $translator->choice($choice, $count, ['count' => $shown]);
        }

        return $shown.' '.$plural;
    }

    private static function pattern(string $format): string
    {
        $formats = [...self::DATES, ...(array) Settings::get('dates', [])];

        return $formats[$format] ?? $format;
    }

    private static function carbon(DateTimeInterface|string|int|null $date, ?string $timezone = null): ?CarbonImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        $carbon = match (true) {
            $date instanceof DateTimeInterface => CarbonImmutable::instance($date),
            is_int($date) => CarbonImmutable::createFromTimestamp($date),
            default => CarbonImmutable::parse($date),
        };

        if ($timezone === null) {
            $container = Container::getInstance();
            $timezone = $container->bound('config') ? $container->make('config')->get('app.timezone') : null;
        }

        return $carbon->setTimezone($timezone ?: 'UTC')->locale(Settings::locale());
    }
}
