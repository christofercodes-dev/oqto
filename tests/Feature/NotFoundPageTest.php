<?php

namespace Tests\Feature;

use Tests\TestCase;

class NotFoundPageTest extends TestCase
{
    public function test_unknown_pages_show_the_custom_404_with_the_right_status(): void
    {
        $this->get('/den-har-sidan-finns-inte')
            ->assertNotFound()
            ->assertSee('Den här sidan har gått under')
            ->assertSee('/lottie/dead.json', false)
            ->assertSee('href="/"', false);
    }

    public function test_the_404_animation_file_exists_and_is_valid_json(): void
    {
        $path = public_path('lottie/dead.json');

        $this->assertFileExists($path);
        $this->assertIsArray(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR));
    }
}
