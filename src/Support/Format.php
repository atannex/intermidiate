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
 *     Format::count(3, 'story');           // "3 stories"
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

    /** "Ma Ngum" → "MN"; "ngum" → "N". */
    public static function initials(?string $name, int $letters = 2): string
    {
        $words = preg_split('/[\s\-_.]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) > $letters) {
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
     * or a format string.
     */
    public static function date(DateTimeInterface|string|int|null $date, string $format = 'medium'): ?string
    {
        $date = self::carbon($date);

        return $date?->translatedFormat(self::pattern($format));
    }

    /** ISO 8601, for <time datetime> and JSON-LD. */
    public static function iso(DateTimeInterface|string|int|null $date): ?string
    {
        return self::carbon($date)?->toIso8601String();
    }

    /** "3 hours ago" for recent dates, the formatted date after $days. */
    public static function relative(DateTimeInterface|string|int|null $date, int $days = 7, string $format = 'medium'): ?string
    {
        $date = self::carbon($date);

        if ($date === null) {
            return null;
        }

        return abs($date->diffInDays(CarbonImmutable::now(), true)) < $days ? $date->diffForHumans() : $date->translatedFormat(self::pattern($format));
    }

    /**
     * "1 story", "3 stories". A translation for ":count stories" (or the
     * pipe form ":count story|:count stories") is used when there is one.
     */
    public static function count(int|float $count, string $noun): string
    {
        $plural = Str::plural($noun, (int) $count);
        $key = ':count '.$plural;
        $container = Container::getInstance();

        if ($container->bound('translator')) {
            $translator = $container->make('translator');
            $choice = ':count '.Str::singular($noun).'|:count '.Str::plural($noun);

            if ($translator->has($choice)) {
                return $translator->choice($choice, $count, ['count' => $count]);
            }

            if ($translator->has($key)) {
                return $translator->get($key, ['count' => $count]);
            }
        }

        return $count.' '.$plural;
    }

    private static function pattern(string $format): string
    {
        $formats = [...self::DATES, ...(array) Settings::get('dates', [])];

        return $formats[$format] ?? $format;
    }

    private static function carbon(DateTimeInterface|string|int|null $date): ?CarbonImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        $carbon = match (true) {
            $date instanceof DateTimeInterface => CarbonImmutable::instance($date),
            is_int($date) => CarbonImmutable::createFromTimestamp($date),
            default => CarbonImmutable::parse($date),
        };

        $container = Container::getInstance();
        $timezone = $container->bound('config') ? $container->make('config')->get('app.timezone') : null;

        return $carbon->setTimezone($timezone ?: 'UTC')->locale(Settings::locale());
    }
}
