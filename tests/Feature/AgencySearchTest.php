<?php

namespace Tests\Feature;

use App\Services\Byrakartan\AgencySearch;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgencySearchTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function cases(): array
    {
        return [
            'mellanrum i stället för bindestreck' => ['A-Count Sweden AB', 'a count', true],
            'ihopskrivet' => ['A-Count Sweden AB', 'acount', true],
            'med bindestreck' => ['A-Count Sweden AB', 'a-count', true],
            'versaler' => ['A-Count Sweden AB', 'A COUNT', true],
            'å, ä och ö utan prickar' => ['Vass Byrå Umeå Malmö', 'vass byra umea malmo', true],
            'med prickar fast byrån har dem' => ['Kvarter Orebro', 'Örebro', true],
            'orden i annan ordning' => ['Vass Byrå Umeå', 'umea vass', true],
            'del av ett ord' => ['Redovisningsbyrån i Lund', 'redovis', true],
            'och-tecken' => ['Fokus Revision & Redovisning', 'revision och redovisning', false],
            'tecknet & ignoreras' => ['Fokus Revision & Redovisning', 'revision redovisning', true],
            'ett stavfel' => ['Kvarter Byrå Örebro', 'kvarer', true],
            'ett stavfel i ett längre ord' => ['Fokus Revision', 'revisoin', true],
            'två stavfel i ett långt ord' => ['Redovisningsbyrån', 'redovisnignsbyran', true],
            'ett kort ord måste stämma' => ['Rak Ekonomi Malmö', 'rok', false],
            'helt fel' => ['Kvarter Byrå Örebro', 'xyzzy', false],
            'ett ord av två saknas' => ['Vass Byrå Umeå', 'vass lund', false],
        ];
    }

    /**
     * @dataProvider cases
     */
    #[DataProvider('cases')]
    public function test_it_finds_what_a_person_means(string $haystack, string $query, bool $expected): void
    {
        $this->assertSame($expected, ($score = (new AgencySearch)->score($haystack, $query)) !== null && $score > 0, "«{$query}» mot «{$haystack}» gav {$score}");
    }

    public function test_the_closer_the_match_the_higher_the_score(): void
    {
        $search = new AgencySearch;

        $exact = $search->score('Kvarter Byrå', 'kvarter');
        $anyOrder = $search->score('Byrå Kvarter Örebro', 'orebro kvarter');
        $noSpaces = $search->score('A-Count Sweden', 'a count');
        $typo = $search->score('Kvartet Ekonomi', 'kvarter');

        $this->assertSame(4, $exact);
        $this->assertSame(3, $anyOrder);
        $this->assertSame(4, $noSpaces);
        $this->assertSame(1, $typo);
        $this->assertSame(0, $search->score('Rak Ekonomi', 'zzzz'));
    }

    public function test_a_query_with_nothing_to_search_on_filters_nothing_away(): void
    {
        $search = new AgencySearch;

        foreach (['', '   ', '&', '-', '!!!'] as $query) {
            $this->assertNull($search->score('Kvarter Byrå', $query), "«{$query}»");
        }
    }

    public function test_it_simplifies_text(): void
    {
        $search = new AgencySearch;

        $this->assertSame('a count sweden ab', $search->normalize('A-Count  Sweden AB'));
        $this->assertSame('vass byra umea', $search->normalize('Vass Byrå Umeå'));
        $this->assertSame('fokus revision redovisning', $search->normalize('Fokus Revision & Redovisning'));
    }
}
