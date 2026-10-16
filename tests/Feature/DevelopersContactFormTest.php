<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Statamic\Facades\Form;
use Tests\TestCase;

class DevelopersContactFormTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    protected array $existingSubmissionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.zapier.webhooks.developers_contact' => 'https://hooks.example.test/developers']);

        // Formuläret sparar inskick (store: true). Testerna tar bara bort de inskick
        // de själva skapar, så att inget lokalt (eller riktigt) inskick rörs.
        $this->existingSubmissionIds = $this->submissionIds();
    }

    protected function tearDown(): void
    {
        Form::find('developers_contact')->submissions()
            ->reject(fn ($submission) => in_array($submission->id(), $this->existingSubmissionIds, true))
            ->each->delete();

        parent::tearDown();
    }

    /**
     * @return array<int, string>
     */
    protected function submissionIds(): array
    {
        return Form::find('developers_contact')->submissions()->map->id()->values()->all();
    }

    public function test_the_form_no_longer_sends_email(): void
    {
        $this->assertEmpty(Form::find('developers_contact')->email());
    }

    public function test_a_submission_goes_to_the_webhook_and_not_to_email(): void
    {
        Mail::fake();
        Http::fake(['hooks.example.test/*' => Http::response('ok')]);

        $this->postJson('/!/forms/developers_contact', [
            'name' => 'Test Testsson',
            'email' => 'test@example.com',
            'company' => 'Testbolaget',
            'message' => 'Hej, vi vill koppla på er.',
            'honeypot' => '',
        ])->assertSuccessful();

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.test/developers'
            && $request['form'] === 'developers_contact'
            && $request['email'] === 'test@example.com'
            && $request['company'] === 'Testbolaget');

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_a_submission_is_still_saved_in_the_control_panel(): void
    {
        Mail::fake();
        Http::fake(['hooks.example.test/*' => Http::response('ok')]);

        $this->assertTrue(Form::find('developers_contact')->store(), 'Formuläret måste spara inskick (store: true).');

        $this->postJson('/!/forms/developers_contact', [
            'name' => 'Sparad Person',
            'email' => 'sparad@example.com',
            'company' => 'Sparbolaget',
            'message' => 'Det här ska synas under Forms i kontrollpanelen.',
            'honeypot' => '',
        ])->assertSuccessful();

        $saved = Form::find('developers_contact')->submissions()
            ->first(fn ($submission) => $submission->get('email') === 'sparad@example.com');

        $this->assertNotNull($saved, 'Inskicket sparades inte.');
        $this->assertSame('Sparad Person', $saved->get('name'));
        $this->assertSame('Sparbolaget', $saved->get('company'));
    }

    public function test_the_visitor_still_succeeds_if_the_webhook_is_down(): void
    {
        Mail::fake();
        Http::fake(['hooks.example.test/*' => Http::response('fel', 500)]);

        $this->postJson('/!/forms/developers_contact', [
            'name' => 'Test Testsson',
            'email' => 'test@example.com',
            'message' => 'Hej',
            'honeypot' => '',
        ])->assertSuccessful();
    }
}
