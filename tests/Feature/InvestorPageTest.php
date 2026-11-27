<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Statamic\Facades\Form;
use Statamic\Facades\Nav;
use Tests\TestCase;

class InvestorPageTest extends TestCase
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $existingSubmissionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Testerna tar bara bort de inskick de själva skapar.
        foreach (['investor_followup', 'investor_rsvp'] as $handle) {
            $this->existingSubmissionIds[$handle] = Form::find($handle)->submissions()->map->id()->values()->all();
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->existingSubmissionIds as $handle => $ids) {
            Form::find($handle)->submissions()
                ->reject(fn ($submission) => in_array($submission->id(), $ids, true))
                ->each->delete();
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'interested' => 'yes',
            'level' => '1-2',
            'company' => 'Testbolaget AB',
            'name' => 'Test Testsson',
            'email' => 'test-'.uniqid().'@example.com',
            'honeypot' => '',
        ], $overrides);
    }

    protected function saved(string $email): mixed
    {
        return Form::find('investor_followup')->submissions()
            ->first(fn ($submission) => $submission->get('email') === $email);
    }

    public function test_the_page_renders_the_follow_up_questions(): void
    {
        $this->get('/investerartraff')
            ->assertOk()
            ->assertSee('Tack för ert intresse för Oqto')
            ->assertSee('Är du fortsatt intresserad av att bli kontaktad med emissionsinformation?')
            ->assertSee('0,5–1 MSEK')
            ->assertSee('1–2 MSEK')
            ->assertSee('data-value="other"', false)
            ->assertSee('name="level_other"', false)
            ->assertDontSee('3–5 MSEK')
            ->assertDontSee('5+ MSEK')
            ->assertSee('Inga förpliktelser')
            ->assertSee('/!/forms/investor_followup', false)
            ->assertDontSee('/!/forms/investor_rsvp', false);
    }

    public function test_the_page_is_hidden_from_search_engines(): void
    {
        $response = $this->get('/investerartraff')->assertOk();

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false);
    }

    public function test_regular_pages_are_not_hidden_from_search_engines(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertFalse($response->headers->has('X-Robots-Tag'));
        $response->assertDontSee('name="robots"', false);
    }

    public function test_the_page_is_not_listed_in_the_navigation_or_robots_txt(): void
    {
        $this->assertStringNotContainsString('investerartraff', json_encode(Nav::find('main')->trees()->map->tree()->all()));

        $robots = public_path('robots.txt');

        if (is_file($robots)) {
            $this->assertStringNotContainsString('investerartraff', file_get_contents($robots));
        }
    }

    public function test_the_form_saves_submissions_and_sends_no_email(): void
    {
        $this->assertTrue(Form::find('investor_followup')->store());
        $this->assertEmpty(Form::find('investor_followup')->email());

        Mail::fake();

        $this->postJson('/!/forms/investor_followup', $this->payload())->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_an_interested_answer_is_saved_with_the_level(): void
    {
        $payload = $this->payload(['level' => '0.5-1', 'phone' => '070-123 45 67']);

        $this->postJson('/!/forms/investor_followup', $payload)->assertSuccessful();

        $saved = $this->saved($payload['email']);

        $this->assertNotNull($saved);
        $this->assertSame('yes', $saved->get('interested'));
        $this->assertSame('0.5-1', $saved->get('level'));
        $this->assertSame('Testbolaget AB', $saved->get('company'));
        $this->assertSame('Test Testsson', $saved->get('name'));
        $this->assertSame('070-123 45 67', $saved->get('phone'));
    }

    public function test_an_interested_answer_needs_a_valid_level(): void
    {
        $this->postJson('/!/forms/investor_followup', $this->payload(['level' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['level']);

        foreach (['100', '3-5', '5+'] as $level) {
            $this->postJson('/!/forms/investor_followup', $this->payload(['level' => $level]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['level']);
        }
    }

    public function test_every_level_on_the_page_is_accepted(): void
    {
        foreach (['0.5-1', '1-2'] as $level) {
            $this->postJson('/!/forms/investor_followup', $this->payload(['level' => $level]))->assertSuccessful();
        }
    }

    public function test_other_needs_a_free_text_level_which_is_saved(): void
    {
        $this->postJson('/!/forms/investor_followup', $this->payload(['level' => 'other']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['level_other']);

        $payload = $this->payload(['level' => 'other', 'level_other' => 'Runt 2,5 MSEK']);

        $this->postJson('/!/forms/investor_followup', $payload)->assertSuccessful();

        $saved = $this->saved($payload['email']);

        $this->assertSame('other', $saved->get('level'));
        $this->assertSame('Runt 2,5 MSEK', $saved->get('level_other'));
    }

    public function test_a_not_interested_answer_needs_no_level(): void
    {
        $payload = $this->payload(['interested' => 'no', 'level' => '']);

        $this->postJson('/!/forms/investor_followup', $payload)->assertSuccessful();

        $this->assertSame('no', $this->saved($payload['email'])->get('interested'));
    }

    public function test_company_contact_person_and_a_valid_email_are_required(): void
    {
        $this->postJson('/!/forms/investor_followup', $this->payload([
            'interested' => '',
            'company' => '',
            'name' => '',
            'email' => 'inte-en-epost',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['interested', 'company', 'name', 'email']);
    }

    public function test_ajax_errors_use_the_format_the_page_script_reads(): void
    {
        // Webbläsaren skickar X-Requested-With; Statamic svarar då med 400 och en lista i "errors".
        $this->postJson('/!/forms/investor_followup', $this->payload(['company' => '']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(400)
            ->assertJsonStructure(['errors' => [0]]);
    }

    public function test_the_old_signup_form_is_closed_but_still_exists_for_its_answers(): void
    {
        $this->assertNotNull(Form::find('investor_rsvp'));

        $before = Form::find('investor_rsvp')->submissions()->count();

        $this->postJson('/!/forms/investor_rsvp', [
            'attending' => 'yes',
            'joins_dinner' => 'yes',
            'needs_accommodation' => 'no',
            'share_consent' => 'yes',
            'attendee1_name' => 'Test Testsson',
            'attendee1_email' => 'test-'.uniqid().'@example.com',
            'honeypot' => '',
        ])->assertStatus(422)
            ->assertJsonPath('errors.attending.0', 'Anmälan till investerarträffen är stängd.');

        $this->assertSame($before, Form::find('investor_rsvp')->submissions()->count());
    }

    public function test_a_submission_is_sent_to_the_webhook_when_configured(): void
    {
        config(['services.zapier.webhooks.investor_followup' => 'https://hooks.example.test/investor']);
        Http::fake(['hooks.example.test/*' => Http::response('ok')]);

        $payload = $this->payload();

        $this->postJson('/!/forms/investor_followup', $payload)->assertSuccessful();

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.test/investor'
            && $request['form'] === 'investor_followup'
            && $request['email'] === $payload['email']
            && $request['level'] === '1-2');
    }

    public function test_nothing_is_sent_to_a_webhook_when_none_is_configured(): void
    {
        config(['services.zapier.webhooks.investor_followup' => null]);
        Http::fake();

        $this->postJson('/!/forms/investor_followup', $this->payload())->assertSuccessful();

        Http::assertNothingSent();
    }
}
