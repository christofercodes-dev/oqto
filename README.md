<p align="center">
<picture>
    <source srcset="https://statamic.com/assets/branding/squircle/statamic-logo-lime-white.svg" media="(prefers-color-scheme: dark)">
    <img align="center" width="350" alt="Statamic Logo" src="https://statamic.com/assets/branding/squircle/statamic-logo-lime.svg">
</picture>
</p>

## About Statamic

Statamic is the flat-first, Laravel + Git powered CMS designed for building beautiful, easy to manage websites.

> [!NOTE]
> This repository contains the code for a fresh Statamic project that is installed via the Statamic CLI tool.
>
> The code for the Statamic Composer package itself can be found at the [Statamic core package repository][cms-repo].


## Integrationer - nattlig import från S3

En extern .NET-tjänst (Sidekick) dumpar nattligen en `applications.json`-fil
(`{ generatedAt, applications: [...] }`) plus bilder i en `images/`-mapp till
en S3-bucket. `App\Services\Integrations\IntegrationsImporter` synkar den
datan in i `integrations`-collectionen: nya poster skapas, ändrade uppdateras,
borttagna avpubliceras (raderas aldrig). Om `generatedAt` är oförändrat sedan
förra körningen hoppas hela importen över.

**Miljövariabler** (se `.env.example`):

```
INTEGRATIONS_S3_KEY=
INTEGRATIONS_S3_SECRET=
INTEGRATIONS_S3_REGION=
INTEGRATIONS_S3_BUCKET=
INTEGRATIONS_S3_ROOT_PREFIX=
INTEGRATIONS_S3_URL=
INTEGRATIONS_S3_JSON_PATH=applications.json
```

`INTEGRATIONS_S3_ROOT_PREFIX` används när samma bucket delas mellan miljöer
(t.ex. `production`/`staging`) och prefixas automatiskt på både
`applications.json` och `images/`. Importen kräver bara `s3:GetObject` -
den listar aldrig bucketen, eftersom `applications.json` redan innehåller
kompletta relativa sökvägar (`avatarKey`/`mediaImageKeys`) till bilderna.

Bilderna laddas aldrig ner eller kopieras - asset-containern `integrations`
(`content/assets/integrations.yaml`) pekar direkt på samma S3-disk, så
Statamic läser dem i realtid från bucketen.

**Bucketen är privat** (ingen anonym läsning), så `{{ avatar:url }}` och
`{{ media }}...{{ url }}...{{ /media }}` returnerar `null` - Statamic
räknar containern som privat eftersom disken saknar `url`-konfiguration
(se `config/filesystems.php`). Använd istället den egna modifiern för att
generera en tillfällig, förhandssignerad URL:

```antlers
{{ avatar:signed_url }}
{{ avatar:signed_url minutes="60" }}   {# giltighetstid i minuter, default 30 #}

{{ media }}
    <img src="{{ signed_url }}">
{{ /media }}
```

Se `app/Modifiers/SignedUrl.php`. Om bucketen i framtiden görs publik (t.ex.
via en CloudFront-distribution) räcker det att sätta `INTEGRATIONS_S3_URL`,
så blir containern "accessible" igen och vanliga `:url`-anrop fungerar direkt.

**Köra importen manuellt lokalt:**

```bash
php artisan integrations:import
```

I produktion körs kommandot automatiskt varje natt kl 03:00 via
`routes/console.php`. Det kräver att servern har en cron-rad som kör
`php artisan schedule:run` varje minut (på Forge: slå på sitans
"Scheduler"-inställning).


## Supportsidor (status, kontakt och guider från Intercom)

`/support` är sajtens supportsida: den visar om appen är uppe (driftstatus),
vägar till kontakt om något strular och guiderna för att flytta till Oqto.
Guiderna ligger under `/support/{kategori}` och `/support/{kategori}/{artikel}`
och hämtas från Intercoms API (cachas) - inget innehåll skrivs till `content/`
eller git. `/support/status` leder vidare (301) till `/support`.

**Driftstatus.** Servern kontrollerar `STATUS_APP_URL` (standard
`https://app.oqto.se`), och sidans skript hämtar resultatet från
`/support/status/check` var 30:e sekund. Svar under 500 = uppe, långsammare än
`STATUS_SLOW_MS` = trögt, 5xx/timeout = störning. Resultatet cachas
`STATUS_CACHE_SECONDS` (20 s). Det är en kontroll av att appen svarar, inte av
att varje funktion fungerar.

**Miljövariabler** (bara i `.env` på servern, aldrig i git):

| Variabel | Vad |
| --- | --- |
| `INTERCOM_ACCESS_TOKEN` | Access token (Intercom > Developer Hub > appen > Authentication) |
| `INTERCOM_CLIENT_SECRET` | Client secret (appen > Basic information), verifierar webhooken |
| `INTERCOM_API_URL` | `https://api.intercom.io` (EU: `https://api.eu.intercom.io`) |
| `INTERCOM_LOCALE` | Föredraget språk för översättningar, standard `sv` |
| `INTERCOM_CACHE_TTL` | Cachetid i sekunder, standard 3600 |
| `INTERCOM_COLLECTION_IDS` | Bara artiklar i dessa samlingar (kommaseparerade id:n), t.ex. `1445236` för "Flytta till Oqto". Tomt = alla |
| `INTERCOM_FEATURED_ARTICLE_IDS` | De guider som visas bredvid varandra på `/support` (artikel-id, i ordning). Tomt = de två första i flytt-kategorin |
| `STATUS_APP_URL` / `STATUS_TIMEOUT` / `STATUS_SLOW_MS` / `STATUS_CACHE_SECONDS` | Driftstatusens adress, timeout (5), gräns för "trögt" i ms (3000) och cache (20) |

**Hur det hänger ihop**

- `App\Services\Intercom\HelpCenter` hämtar samlingar och publicerade artiklar,
  gör om dem till kategorier (översta samlingen) med sektioner (underliggande
  samling), rensar artikel-HTML och cachar resultatet. Misslyckas en hämtning
  används senaste lyckade kopian.
- `php artisan intercom:sync-help-center` hämtar om manuellt. Schemaläggaren kör
  det var 30:e minut (kräver `schedule:run` på servern, se ovan) så länge
  `INTERCOM_ACCESS_TOKEN` finns.
- `POST /webhooks/intercom` tar emot Intercoms webhooks. Anropet verifieras med
  headern `X-Hub-Signature` (HMAC-SHA1 av body med client secret). Giltiga anrop
  läser om hjälpcentret (högst en gång per 30 sekunder); `ping` bekräftas bara.
  Registrera `https://oqto.se/webhooks/intercom` under Developer Hub > appen >
  Webhooks, och välj bara de händelser ni behöver - varje händelse kan utlösa en
  omhämtning.
- Utseendet ligger i `resources/views/support/` och `resources/css/support/`.
  Tester: `php artisan test tests/Feature/IntercomHelpCenterTest.php`.

## Learning Statamic

Statamic has extensive [documentation][docs]. We dedicate a significant amount of time and energy every day to improving them, so if something is unclear, feel free to open issues for anything you find confusing or incomplete. We are happy to consider anything you feel will make the docs and CMS better.

## Support

We provide official developer support on [Statamic Pro](https://statamic.com/pricing) projects. Community-driven support is available via [GitHub Discussions](https://github.com/statamic/cms/discussions) and in [Discord][discord].


## Contributing

Thank you for considering contributing to Statamic! We simply ask that you review the [contribution guide][contribution] before you open issues or send pull requests.


## Code of Conduct

In order to ensure that the Statamic community is welcoming to all and generally a rad place to belong, please review and abide by the [Code of Conduct](https://github.com/statamic/cms/wiki/Code-of-Conduct).


## Important Links

- [Statamic Main Site](https://statamic.com)
- [Statamic Documentation][docs]
- [Statamic Core Package Repo][cms-repo]
- [Statamic Migrator](https://github.com/statamic/migrator)
- [Statamic Discord][discord]

[docs]: https://statamic.dev/
[discord]: https://statamic.com/discord
[contribution]: https://github.com/statamic/cms/blob/master/CONTRIBUTING.md
[cms-repo]: https://github.com/statamic/cms
