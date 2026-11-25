<?php

namespace App\Services\Byrakartan;

use Illuminate\Support\Str;

/**
 * Förlåtande textsökning bland byråerna, så att man hittar rätt även om man skriver lite annorlunda än byrån
 * stavar sig. "a count" ska hitta "A-Count Sweden AB", "malmo" ska hitta Malmö och "vass umea" ska hitta
 * "Vass Byrå Umeå".
 *
 * Texten jämförs i en förenklad form (gemener, å/ä/ö som a/a/o, allt som inte är bokstäver eller siffror blir
 * mellanrum). En byrå matchar om något av detta stämmer, och får då poäng efter hur nära träffen är:
 *   4  hela frågan finns som en följd i texten
 *   3  alla ord i frågan finns någonstans i texten, i valfri ordning
 *   2  frågan stämmer när mellanrummen tas bort ("a count" ~ "a-count" ~ "acount")
 *   1  alla ord stämmer när man tillåter ett enstaka stavfel
 *   0  ingen träff
 */
class AgencySearch
{
    /** Förenklad text: gemener, utan diakritiska tecken och med mellanrum i stället för skiljetecken. */
    public function normalize(string $text): string
    {
        $text = strtolower(Str::ascii($text));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $text));
    }

    /**
     * @return int|null null om frågan inte innehåller något att söka på (då filtreras ingenting bort)
     */
    public function score(string $haystack, string $query): ?int
    {
        $needle = $this->normalize($query);

        if ($needle === '') {
            return null;
        }

        $text = $this->normalize($haystack);

        if (str_contains($text, $needle)) {
            return 4;
        }

        $tokens = explode(' ', $needle);

        if ($this->all($tokens, fn (string $token) => str_contains($text, $token))) {
            return 3;
        }

        if (str_contains(str_replace(' ', '', $text), str_replace(' ', '', $needle))) {
            return 2;
        }

        $words = explode(' ', $text);

        if ($this->all($tokens, fn (string $token) => str_contains($text, $token) || $this->closeToAny($token, $words))) {
            return 1;
        }

        return 0;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    protected function all(array $tokens, callable $test): bool
    {
        foreach ($tokens as $token) {
            if (! $test($token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ett stavfel tillåts i ord med minst fyra tecken, två i ord med minst åtta. Korta ord måste stämma exakt,
     * annars skulle nästan allt matcha.
     *
     * @param  array<int, string>  $words
     */
    protected function closeToAny(string $token, array $words): bool
    {
        $length = strlen($token);
        $allowed = $length >= 8 ? 2 : ($length >= 4 ? 1 : 0);

        if ($allowed === 0) {
            return false;
        }

        foreach ($words as $word) {
            if (abs(strlen($word) - $length) <= $allowed && levenshtein($token, $word) <= $allowed) {
                return true;
            }
        }

        return false;
    }
}
