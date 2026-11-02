<?php

namespace App\Services\Properties;

use Illuminate\Support\Str;

/**
 * Brings listing text from outside feeds into one house style, so imported listings read like
 * ones typed into the form: titles without SHOUTING / "!!!" / emoji runs, key features one per
 * line, and descriptions as clean HTML paragraphs and bullet lists (what the TinyMCE editor
 * stores). Text that is already HTML keeps its structure, minus anything the editor wouldn't allow.
 */
class ListingTextNormalizer
{
    private const ALLOWED_TAGS = '<p><br><ul><ol><li><strong><b><em><i><h3><h4>';
    private const SMALL_WORDS = ['a', 'an', 'and', 'at', 'by', 'for', 'in', 'of', 'on', 'or', 'the', 'to', 'with'];

    public function title(?string $title): string
    {
        $title = $this->line((string) $title);
        $title = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $title);
        $title = preg_replace('/([!?.,*~])\1+/', '$1', $title);
        $title = trim(preg_replace('/\s+/', ' ', $title), " \t-|*!");

        // All capitals ("STUNNING 2BR IN JVC") → Title Case, keeping short codes (JVC, BR) but not small words.
        $letters = preg_replace('/[^\p{L}]/u', '', $title);
        if (mb_strlen($letters) > 8 && mb_strtoupper($letters) === $letters) {
            $title = preg_replace_callback('/\p{L}[\p{L}\']*/u', fn ($m) => match (true) {
                in_array(mb_strtolower($m[0]), self::SMALL_WORDS, true) => mb_strtolower($m[0]),
                mb_strlen($m[0]) <= 3 => $m[0],
                default => Str::ucfirst(mb_strtolower($m[0])),
            }, $title);
            $title = Str::ucfirst($title);
        }

        return Str::limit($title, 255, '');
    }

    /** "Pool; Gym | Maid's room" or one per line → one feature per line, no bullets. */
    public function keyFeatures(?string $features): string
    {
        $items = preg_split('/\r\n|\r|\n|;|\|/', (string) $features);

        return collect($items)->map(fn ($i) => $this->line(preg_replace('/^\s*([-*•▪●✓✔]|\d+[.)])\s*/u', '', $i)))
            ->filter()->unique()->take(20)->implode("\n");
    }

    /** Plain text (paragraphs, "- " bullet lines) or HTML → editor HTML. */
    public function description(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        if ($text !== strip_tags($text)) {
            return trim(strip_tags($text, self::ALLOWED_TAGS));
        }

        $html = [];
        foreach (preg_split('/(\r\n|\r|\n)\s*(\r\n|\r|\n)+/', $text) as $block) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $block))));
            if (!$lines) {
                continue;
            }
            $bullets = array_filter($lines, fn ($l) => preg_match('/^([-*•▪●✓✔]|\d+[.)])\s+/u', $l));
            if (count($bullets) >= 2 && count($bullets) >= count($lines) - 1) {
                // A heading line ("Features:") above the bullets stays a paragraph.
                if (count($bullets) < count($lines)) {
                    $html[] = '<p>' . e(array_shift($lines)) . '</p>';
                }
                $html[] = '<ul>' . implode('', array_map(fn ($l) => '<li>' . e(preg_replace('/^([-*•▪●✓✔]|\d+[.)])\s+/u', '', $l)) . '</li>', $lines)) . '</ul>';
            } else {
                $html[] = '<p>' . implode('<br>', array_map('e', $lines)) . '</p>';
            }
        }

        return implode("\n", $html);
    }

    /** Paragraphs and bullet points (e.g. the AI copywriter's) → editor HTML. */
    public function fromParts(array $paragraphs, array $bullets = []): string
    {
        $html = array_map(fn ($p) => '<p>' . e($this->line($p)) . '</p>', array_filter($paragraphs, fn ($p) => is_string($p) && trim($p) !== ''));
        $bullets = array_filter($bullets, fn ($b) => is_string($b) && trim($b) !== '');
        if ($bullets) {
            $html[] = '<ul>' . implode('', array_map(fn ($b) => '<li>' . e($this->line($b)) . '</li>', $bullets)) . '</ul>';
        }

        return implode("\n", $html);
    }

    private function line(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace(['**', '__'], '', $text)));
    }
}
