<?php

namespace Tests\Feature;

use Tests\TestCase;

class ForetagareIndexTest extends TestCase
{
    public function test_the_index_looks_like_funktioner_and_lists_the_features_as_blocks(): void
    {
        $this->get('/for-foretagare')
            ->assertOk()
            ->assertSee('funktioner-hero-section', false)
            ->assertSee('funktioner-hero-phone', false)
            ->assertSee('id="funktioner"', false)
            ->assertSee('fi-block', false)
            ->assertSee('Läs mer och prova', false);
    }

    public function test_the_hero_leads_to_the_message_for_the_agency_and_the_list(): void
    {
        $this->get('/for-foretagare')
            ->assertOk()
            ->assertSee('href="#kontakta-byran"', false)
            ->assertSee('Berätta för din byrå', false)
            ->assertSee('href="#funktioner"', false);
    }

    public function test_there_is_a_message_generator_and_a_not_yet_ready_option_without_agency(): void
    {
        $this->get('/for-foretagare')
            ->assertOk()
            ->assertSee('id="kontakta-byran"', false)
            ->assertSee('data-agency-message', false)
            ->assertSee('data-message-text', false)
            ->assertDontSee('data-message-field', false)
            ->assertSee('data-message-copy', false)
            ->assertDontSee('data-message-mail', false)
            ->assertSee('Jag har ingen byrå', false)
            ->assertSee('Kommer snart', false);
    }

    public function test_the_message_script_is_loaded_for_the_generator(): void
    {
        $site = file_get_contents(base_path('resources/js/site.js'));

        $this->assertStringContainsString("import('./foretagare-message.js')", $site);
        $this->assertFileExists(base_path('resources/js/foretagare-message.js'));
    }
}
