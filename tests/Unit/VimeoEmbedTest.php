<?php

namespace Tests\Unit;

use App\Modifiers\VimeoEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VimeoEmbedTest extends TestCase
{
    #[DataProvider('links')]
    public function test_it_turns_vimeo_links_into_the_start_of_the_player_url(string $link, string $expected): void
    {
        $this->assertSame($expected, (new VimeoEmbed)->index($link, [], []));
    }

    public static function links(): array
    {
        return [
            'dold video med kod' => ['https://vimeo.com/1223949720/6b9a7c897a?share=copy&fl=sv&fe=ci', '1223949720?h=6b9a7c897a&'],
            'öppen video' => ['https://vimeo.com/1223949720?share=copy&fl=sv&fe=ci', '1223949720?'],
            'öppen video utan parametrar' => ['https://vimeo.com/1223949720', '1223949720?'],
            'spelarlänk med kod' => ['https://player.vimeo.com/video/1223949720?h=6b9a7c897a', '1223949720?h=6b9a7c897a&'],
            'enbart id' => ['1223949720', '1223949720?'],
            'blanksteg runt' => ["  https://vimeo.com/1223949720/6b9a7c897a \n", '1223949720?h=6b9a7c897a&'],
        ];
    }
}
