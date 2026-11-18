<?php

namespace App\Services\Chatbot;

/**
 * Loose matching of what a visitor typed against real names in the data (communities, cities,
 * nearby places) — with no hand-kept alias list, so a new community or place works the day it's
 * added. A typed word matches a name when it:
 *  - is the same word, or one contains the other ("ranch" ~ "ranches"),
 *  - is a small typo of it ("jumeira" ~ "jumeirah", "astor" ~ "aster"),
 *  - is the short form of consecutive words of it, worked out from the name itself
 *    ("jvc" ~ "Jumeirah Village Circle", "dip" ~ "Dubai Investments Park").
 */
class NameMatcher
{
    /** Words that don't identify a place ("the Springs", "Al Furjan area", "near JVC"). */
    private const FILLER = ['the', 'al', 'el', 'area', 'areas', 'community', 'communities', 'near', 'in', 'at',
        'around', 'of', 'and', 'uae', 'to', 'by', 'close', 'nearby', 'next', 'property', 'properties'];

    /** Lower-case meaningful words of a phrase. */
    public static function words(string $text): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/[\s,.\/\-()&\'’]+/u', mb_strtolower($text)) ?: [],
            fn ($w) => mb_strlen($w) >= 2 && !in_array($w, self::FILLER, true)
        )));
    }

    /** Does the typed $word match the name made of $nameWords? */
    public static function wordMatches(string $word, array $nameWords): bool
    {
        foreach ($nameWords as $nameWord) {
            if (self::similar($word, $nameWord)) {
                return true;
            }
        }

        return mb_strlen($word) >= 2 && in_array($word, self::acronyms($nameWords), true);
    }

    /**
     * How well $query names $name: query words found in it, and whether every word of the name is
     * covered (by a word, or by one short form spanning several of its words).
     *
     * @return array{hits: int, covered: bool}
     */
    public static function compare(array $queryWords, array $nameWords): array
    {
        $hits = count(array_filter($queryWords, fn ($w) => self::wordMatches($w, $nameWords)));
        $covered = $nameWords && ($queryWords
            && (count(array_filter($nameWords, fn ($n) => self::anySimilar($n, $queryWords))) === count($nameWords)
                || in_array(implode('', array_map(fn ($n) => mb_substr($n, 0, 1), $nameWords)), $queryWords, true)));

        return ['hits' => $hits, 'covered' => (bool) $covered];
    }

    private static function anySimilar(string $word, array $others): bool
    {
        foreach ($others as $other) {
            if (self::similar($word, $other)) {
                return true;
            }
        }

        return false;
    }

    private static function similar(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        [$short, $long] = mb_strlen($a) <= mb_strlen($b) ? [$a, $b] : [$b, $a];
        if (mb_strlen($short) >= 4 && str_contains($long, $short)) {
            return true;
        }
        // Typos only on Latin words of some length — short words differ by one letter too easily.
        $len = min(strlen($a), strlen($b));
        if ($len < 5 || preg_match('/[^a-z]/', $a . $b)) {
            return false;
        }

        return levenshtein($a, $b) <= ($len >= 8 ? 2 : 1);
    }

    /** Initials of every run of 2+ consecutive words: [jumeirah, village, circle] → jv, jvc, vc. */
    private static function acronyms(array $words): array
    {
        $initials = array_map(fn ($w) => mb_substr($w, 0, 1), $words);
        $out = [];
        for ($i = 0; $i < count($initials); $i++) {
            for ($j = $i + 2; $j <= count($initials); $j++) {
                $out[] = implode('', array_slice($initials, $i, $j - $i));
            }
        }

        return $out;
    }
}
