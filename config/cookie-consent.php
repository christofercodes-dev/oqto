<?php

// Defaults written for the EU's RGPD/GDPR (Regulamento (UE) 2016/679). A
// host app overrides any of this either by editing this published config
// file, or via the CP settings screen — CP edits win (see
// Settings\CookieConsentSettings) and are stored separately per site under
// content/cookie-consent/{site}/settings.yaml, so upgrading the addon never
// clobbers a site's own copy/groups. A site under a different regime (e.g.
// Brazil's LGPD) just edits its own copy in the CP — `legal_basis`'s three
// values (consentimento / legitimo_interesse / obrigacao_legal) map to both
// RGPD Art. 6º and LGPD Art. 7º, so the field itself doesn't need to change.
return [

    // Off entirely — banner/button/scripts tags render nothing, and every
    // group counts as allowed (no consent needed). Useful for a staging site
    // or a jurisdiction where the banner shouldn't show.
    'enabled' => true,

    'version' => 1,

    // Bumping this invalidates every visitor's stored consent, forcing the
    // banner to show again — same mechanism the original addon uses.
    'text' => [
        'title' => 'Vi använder cookies',
        'description' => 'Vi använder cookies för att sajten ska fungera, för att förstå hur den används och, om du godkänner det, för att mäta och förbättra vår marknadsföring.',
        'accept_all' => 'Godkänn alla',
        'reject_all' => 'Endast nödvändiga',
        'customize' => 'Anpassa',
        'save' => 'Spara val',
        'privacy_policy_label' => 'Integritetspolicy',
        'privacy_policy_url' => '/villkor#integritetspolicy',
    ],

    'position' => 'bottom', // bottom | top | bottom-left | bottom-right
    'theme' => 'light', // auto | light | dark

    'button' => [
        'enabled' => true,
        'label' => 'Cookies',
        'aria_label' => 'Cookie-inställningar',
        'position' => 'bottom-left', // bottom-left | bottom-right | top-left | top-right
        'background' => '#ffffff',
        'foreground' => '#6b7280',
        'border' => 'rgba(99, 102, 241, 0.16)',
        'shadow' => '0 8px 24px rgba(0, 0, 0, 0.16)',
        'icon' => 'cookie', // cookie | none | any HTML/SVG string
        'icon_background' => '#ff5500',
        'icon_foreground' => '#ffffff',
    ],

    'groups' => [
        'necessary' => [
            'name' => 'Nödvändiga',
            'description' => 'Krävs för att sajten ska fungera. Kan inte stängas av.',
            'required' => true,
            'default' => true,
            'legal_basis' => 'obrigacao_legal',
            'cookies' => [
                [
                    'name' => 'cookie_consent',
                    'purpose' => 'Sparar dina cookie-val.',
                    'retention' => '12 månader',
                ],
            ],
        ],
        'analytics' => [
            'name' => 'Statistik',
            'description' => 'Hjälper oss att förstå hur sajten används, på aggregerad nivå.',
            'required' => false,
            'default' => false,
            'legal_basis' => 'consentimento',
            'cookies' => [
                [
                    'name' => '_ga, _ga_*',
                    'purpose' => 'Google Analytics: skiljer besökare åt och mäter användning av sajten.',
                    'retention' => '13 månader',
                ],
            ],
        ],
        'marketing' => [
            'name' => 'Marknadsföring',
            'description' => 'Används för att anpassa annonser och mäta kampanjer.',
            'required' => false,
            'default' => false,
            'legal_basis' => 'consentimento',
            'cookies' => [],
        ],
    ],

    // Maps a cookie group to the Google Consent Mode v2 signals it grants.
    // consent-mode.js reads this straight through — no Google-specific PHP.
    'consent_mode' => [
        'necessary' => ['security_storage'],
        'analytics' => ['analytics_storage'],
        'marketing' => ['ad_storage', 'ad_user_data', 'ad_personalization'],
    ],

];
