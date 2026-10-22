<?php

namespace Tests\Feature;

use App\Mail\InvestorRsvpConfirmation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Statamic\Facades\Form;
use Statamic\Facades\Nav;
use Tests\TestCase;

class InvestorPageTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    protected array $existingSubmissionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Testerna tar bara bort de inskick de själva skapar.
        $this->existingSubmissionIds = $this->submissionIds();
    }

    protected function tearDown(): void
    {
        Form::find('investor_rsvp')->submissions()
            ->reject(fn ($submission) => in_array($submission->id(), $this->existingSubmissionIds, true))
            ->each->delete();

        parent::tearDown();
    }

    /**
     * @return array<int, string>
     */
    protected function submissionIds(): array
    {
        return Form::find('investor_rsvp')->submissions()->map->id()->values()->all();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    protected function attendingPayload(array $overrides = []): array
    {
        return array_merge([
            'attending' => 'yes',
            'joins_dinner' => 'yes',
            'needs_accommodation' => 'no',
            'share_consent' => 'yes',
            'attendee1_name' => 'Test Testsson',
            'attendee1_email' => 'test-'.uniqid().'@example.com',
            'honeypot' => '',
        ], $overrides);
    }

    public function test_the_page_renders_with_the_invitation_and_form(): void
    {
        $this->get('/investerartraff')
            ->assertOk()
            ->assertSee('Investerarträff för Oqto')
            ->assertSee('25 november kl. 15.00')
            ->assertSee('Anmäl ditt intresse');
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

    public function test_the_form_saves_submissions_and_has_no_notification_email_configured(): void
    {
        $this->assertTrue(Form::find('investor_rsvp')->store());
        $this->assertEmpty(Form::find('investor_rsvp')->email());
    }

    public function test_an_attending_guest_gets_a_thank_you_email_from_info(): void
    {
        Mail::fake();

        $payload = $this->attendingPayload(['joins_dinner' => 'yes']);

        $this->postJson('/!/forms/investor_rsvp', $payload)->assertSuccessful();

        Mail::assertSent(InvestorRsvpConfirmation::class, 1);

        Mail::assertSent(InvestorRsvpConfirmation::class, function (InvestorRsvpConfirmation $mail) use ($payload) {
            $mail->assertFrom('info@oqto.se');
            $mail->assertTo($payload['attendee1_email']);
            $mail->assertHasSubject('Tack för din anmälan till Investerarträff för Oqto');
            $mail->assertSeeInHtml('Tack för din anmälan!');
            $mail->assertSeeInHtml('25 november kl. 15.00');
            $mail->assertSeeInText('Middag på kvällen: Ja');

            return true;
        });
    }

    public function test_both_attendees_get_an_email_and_the_second_is_told_who_registered_them(): void
    {
        Mail::fake();

        $payload = $this->attendingPayload([
            'attendee2_name' => 'Andra Personen',
            'attendee2_email' => 'andra-'.uniqid().'@example.com',
        ]);

        $this->postJson('/!/forms/investor_rsvp', $payload)->assertSuccessful();

        Mail::assertSent(InvestorRsvpConfirmation::class, 2);

        Mail::assertSent(InvestorRsvpConfirmation::class, function (InvestorRsvpConfirmation $mail) use ($payload) {
            if (! $mail->hasTo($payload['attendee2_email'])) {
                return false;
            }

            $mail->assertSeeInHtml('Test Testsson har anmält dig');

            return true;
        });

        Mail::assertSent(InvestorRsvpConfirmation::class, fn ($mail) => $mail->hasTo($payload['attendee1_email']));
    }

    public function test_a_declined_guest_gets_a_different_email(): void
    {
        Mail::fake();

        $payload = $this->attendingPayload([
            'attending' => 'no',
            'joins_dinner' => '',
            'needs_accommodation' => '',
            'share_consent' => '',
            'info_preference' => 'Telefonsamtal',
        ]);

        $this->postJson('/!/forms/investor_rsvp', $payload)->assertSuccessful();

        Mail::assertSent(InvestorRsvpConfirmation::class, function (InvestorRsvpConfirmation $mail) use ($payload) {
            $mail->assertFrom('info@oqto.se');
            $mail->assertTo($payload['attendee1_email']);
            $mail->assertHasSubject('Tack för ditt svar – Investerarträff för Oqto');
            $mail->assertSeeInHtml('telefonsamtal');
            $mail->assertDontSeeInHtml('Agenda');

            return true;
        });
    }

    public function test_no_email_is_sent_when_the_submission_is_rejected(): void
    {
        Mail::fake();

        $this->postJson('/!/forms/investor_rsvp', $this->attendingPayload(['attendee1_email' => 'fel']))
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_a_broken_mailer_does_not_make_the_signup_fail(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP nere'));

        $payload = $this->attendingPayload();

        $this->postJson('/!/forms/investor_rsvp', $payload)->assertSuccessful();

        $this->assertNotNull(Form::find('investor_rsvp')->submissions()
            ->first(fn ($submission) => $submission->get('attendee1_email') === $payload['attendee1_email']));
    }

    public function test_an_attending_rsvp_is_saved(): void
    {
        Mail::fake();

        $payload = $this->attendingPayload(['company' => 'Testbolaget AB']);

        $this->postJson('/!/forms/investor_rsvp', $payload)->assertSuccessful();

        $saved = Form::find('investor_rsvp')->submissions()
            ->first(fn ($submission) => $submission->get('attendee1_email') === $payload['attendee1_email']);

        $this->assertNotNull($saved);
        $this->assertSame('Testbolaget AB', $saved->get('company'));
    }

    public function test_a_declined_rsvp_needs_an_information_preference(): void
    {
        $payload = $this->attendingPayload([
            'attending' => 'no',
            'joins_dinner' => '',
            'needs_accommodation' => '',
            'share_consent' => '',
        ]);

        $this->postJson('/!/forms/investor_rsvp', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['info_preference']);

        $this->postJson('/!/forms/investor_rsvp', $payload + ['info_preference' => 'Telefonsamtal'])
            ->assertSuccessful();
    }

    public function test_an_attending_rsvp_needs_the_follow_up_answers(): void
    {
        $this->postJson('/!/forms/investor_rsvp', $this->attendingPayload([
            'joins_dinner' => '',
            'needs_accommodation' => '',
            'share_consent' => '',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['joins_dinner'])
            ->assertJsonValidationErrors(['needs_accommodation'])
            ->assertJsonValidationErrors(['share_consent']);
    }

    public function test_name_and_a_valid_email_are_required(): void
    {
        $this->postJson('/!/forms/investor_rsvp', $this->attendingPayload([
            'attendee1_name' => '',
            'attendee1_email' => 'inte-en-epost',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['attendee1_name'])
            ->assertJsonValidationErrors(['attendee1_email']);
    }

    public function test_the_same_email_cannot_register_twice(): void
    {
        $payload = $this->attendingPayload();

        $this->postJson('/!/forms/investor_rsvp', $payload)->assertSuccessful();

        $this->postJson('/!/forms/investor_rsvp', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.attendee1_email.0', 'Det finns redan en anmälan med den e-postadressen.');

        // Ingen skillnad på stora/små bokstäver.
        $this->postJson('/!/forms/investor_rsvp', array_merge($payload, [
            'attendee1_email' => strtoupper($payload['attendee1_email']),
        ]))->assertStatus(422);
    }

    public function test_ajax_errors_use_the_format_the_page_script_reads(): void
    {
        $payload = $this->attendingPayload();

        $this->postJson('/!/forms/investor_rsvp', $payload)->assertSuccessful();

        // Webbläsaren skickar X-Requested-With; Statamic svarar då med 400 och en lista i "errors".
        $this->postJson('/!/forms/investor_rsvp', $payload, ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(400)
            ->assertJsonPath('errors.0', 'Det finns redan en anmälan med den e-postadressen.');
    }

    public function test_a_second_attendee_cannot_reuse_an_existing_or_the_same_email(): void
    {
        $first = $this->attendingPayload();

        $this->postJson('/!/forms/investor_rsvp', $first)->assertSuccessful();

        $this->postJson('/!/forms/investor_rsvp', $this->attendingPayload([
            'attendee2_name' => 'Andra Personen',
            'attendee2_email' => $first['attendee1_email'],
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['attendee2_email']);

        $same = $this->attendingPayload();

        $this->postJson('/!/forms/investor_rsvp', $same + [
            'attendee2_name' => 'Andra Personen',
            'attendee2_email' => $same['attendee1_email'],
        ])->assertStatus(422);
    }

    public function test_a_second_attendee_needs_both_name_and_email(): void
    {
        $this->postJson('/!/forms/investor_rsvp', $this->attendingPayload([
            'attendee2_name' => 'Bara Namn',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['attendee2_email']);

        $this->postJson('/!/forms/investor_rsvp', $this->attendingPayload([
            'attendee2_name' => 'Andra Personen',
            'attendee2_email' => 'andra-'.uniqid().'@example.com',
        ]))->assertSuccessful();
    }

    public function test_a_submission_is_sent_to_the_webhook_when_configured(): void
    {
        config(['services.zapier.webhooks.investor_rsvp' => 'https://hooks.example.test/investor']);
        Http::fake(['hooks.example.test/*' => Http::response('ok')]);

        $payload = $this->attendingPayload();

        $this->postJson('/!/forms/investor_rsvp', $payload)->assertSuccessful();

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.test/investor'
            && $request['form'] === 'investor_rsvp'
            && $request['attendee1_email'] === $payload['attendee1_email']);
    }

    public function test_nothing_is_sent_to_a_webhook_when_none_is_configured(): void
    {
        config(['services.zapier.webhooks.investor_rsvp' => null]);
        Http::fake();

        $this->postJson('/!/forms/investor_rsvp', $this->attendingPayload())->assertSuccessful();

        Http::assertNothingSent();
    }
}
