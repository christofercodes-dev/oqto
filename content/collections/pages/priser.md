---
id: 03c890d5-ba59-469a-9151-dc0807f7f3f6
blueprint: pricing
title: Priser
seo_title: 'Priser & bolagslägen'
seo_description: 'Ingen byrålicens – välj mellan Traditionell, Modern eller Förvaltning och betala per företag och användare. Se alla priser och vad som ingår.'
template: pricing
users:
  - 4dcd5e55-456c-4762-8d2f-b2b41cf595f2
updated_by: 5cd91a7e-9398-4f5a-a175-60c6191660a0
updated_at: 1789980572
pricing_button_text: 'Boka demo'
pricing_button_url: /boka-demo
intro_small_title: Bolagslägen
intro_title: 'Ingen byrålicens, </br>bara olika bolagslägen.'
intro_body: 'Vi har ingen byrålicens och systemet kostar ingenting för er som redovisningsbyrå. Vi tar betalt per företag och användarna på dessa. Välj mellan Traditionell, Modern eller Förvaltning nedan.'
pricing_plans:
  -
    id: SOfAiZFnbi7K6ImrmAgJm
    plan_title: Traditionell
    plan_number: '01'
    description: 'För årskunder utan löpande uppföljning — upp till 1200 verifikat/år.'
    monthly_price: '125'
    verification_limit: '1200 st/år'
    type: pricing_plan
    enabled: true
    highlights:
      - 'Bokföring, fakturering, lön'
      - '1200 verifikat/år'
      - 'Passar bäst kunder med årsbokslut, inte löpande uppföljning'
      - 'Inget månadsavslut, ingen autopilot'
    features:
      - id: 75gNxYSP
        label: Bokföring
      - id: KHbFw3lZ
        label: Försäljning
      - id: KOoVXYsd
        label: Inköp
      - id: fjBTZM6c
        label: Anläggningsregister
      - id: kt7PtmTv
        label: Periodisering
      - id: asBFjRuU
        label: Avtalsfakturering
      - id: aTIJms0D
        label: 'Bank & Skatt'
        tooltip: '1 bankkonto ingår i priset. Fler konton kostar *30 kr/konto/månad*, samma villkor oavsett bolagsläge.'
      - id: qhURE0mO
        label: Lön
        tooltip: 'Lönespecifikation 20kr/st'
      - id: 79vZC93V
        label: 'Attest & Betalning'
        tooltip: 'Kräver en användare'
    recommended: false
  -
    id: eTiJRgHFQetkdFMafsa1S
    plan_title: Modern
    plan_number: '02'
    recommended: true
    description: 'Standardvalet för i princip alla aktiva bolag.'
    highlights:
      - 'Allt i Traditionell'
      - 'Fritt antal verifikat'
      - 'Månadsavslut + autopilot'
      - 'Ger byrån kickback'
    features:
      - id: GudXNrjP
        label: Bokföring
      - id: TD3YBEWL
        label: Försäljning
      - id: TYMjKVvP
        label: Inköp
      - id: GD8amHB6
        label: Anläggningsregister
      - id: 4s3fjGs1
        label: Periodisering
      - id: nwWaVfpg
        label: Avtalsfakturering
      - id: x76qHdWU
        label: 'Bank & Skatt'
        tooltip: '1 bankkonto ingår i priset. Fler konton kostar 30 kr/konto/månad, samma villkor oavsett bolagsläge.'
      - id: Qb0FKvIE
        label: Lön
        tooltip: 'Lönespecifikation 20kr/st'
      - id: zZefki5p
        label: 'Attest & Betalning'
        tooltip: 'Kräver en användare'
      - id: slPypJZZ
        label: Månadsavslut
      - id: tXePboLD
        label: 'Automationer (Autopilot)'
    monthly_price: '250'
    verification_limit: Fritt!
    type: pricing_plan
    enabled: true
comparison_small_title: Jämför
comparison_title: 'Alla bolagslägen sida vid sida.'
comparison_forvaltning_price: '20'
comparison_traditionell_price: '125'
comparison_modern_price: '250'
comparison_categories:
  -
    id: cmp_cat_bokforing
    category_title: 'Bokföring & fakturering'
    type: category
    enabled: true
    rows:
      - id: cmp_row_bokforing
        label: Bokföring
        forvaltning_value: check
        traditionell_value: check
        modern_value: check
        type: row
        enabled: true
      - id: cmp_row_forsaljning
        label: Försäljning
        forvaltning_value: check
        traditionell_value: check
        modern_value: check
        type: row
        enabled: true
      - id: cmp_row_inkop
        label: Inköp
        forvaltning_value: check
        traditionell_value: check
        modern_value: check
        type: row
        enabled: true
      - id: cmp_row_integrationer
        label: Integrationer
        forvaltning_value: check
        traditionell_value: check
        modern_value: check
        type: row
        enabled: true
      - id: cmp_row_anlaggningsregister
        label: Anläggningsregister
        forvaltning_value: cross
        traditionell_value: check
        modern_value: check
        type: row
        enabled: true
      - id: cmp_row_periodisering
        label: Periodisering
        forvaltning_value: cross
        traditionell_value: check
        modern_value: check
        type: row
        enabled: true
      - id: cmp_row_avtalsfakturering
        label: Avtalsfakturering
        forvaltning_value: cross
        traditionell_value: check
        modern_value: check
        type: row
        enabled: true
  -
    id: cmp_cat_bank
    category_title: 'Bank, skatt & lön'
    type: category
    enabled: true
    rows:
      - id: cmp_row_bank_skatt
        label: 'Bank & Skatt'
        forvaltning_value: cross
        traditionell_value: check
        modern_value: check
        type: row
        enabled: true
        detail: '1 bankkonto ingår i priset. Fler konton kostar 30 kr/konto/månad, samma villkor oavsett bolagsläge.'
      - id: cmp_row_lon
        label: Lön
        detail: 'Lönespecifikation kostar 20 kr/st och ingår inte i månadspriset.'
        forvaltning_value: cross
        traditionell_value: check
        modern_value: check
        type: row
        enabled: true
  -
    id: cmp_cat_automatisering
    category_title: Automatisering
    type: category
    enabled: true
    rows:
      - id: cmp_row_attest
        label: 'Attest & Betalning'
        detail: 'Modul för slutkunden att signera betalningar och attestera leverantörsfakturor.'
        forvaltning_value: cross
        traditionell_value: check
        modern_value: check
        type: row
        enabled: true
      - id: cmp_row_manadsavslut
        label: Månadsavslut
        forvaltning_value: cross
        traditionell_value: cross
        modern_value: check
        type: row
        enabled: true
      - id: cmp_row_automationer
        label: 'Automationer (Autopilot)'
        forvaltning_value: cross
        traditionell_value: cross
        modern_value: check
        type: row
        enabled: true
  -
    id: cmp_cat_granser
    category_title: 'Gränser & tillägg'
    type: category
    enabled: true
    rows:
      - id: cmp_row_verifikationsgrans
        label: Verifikationsgräns
        forvaltning_value: '50 st/år'
        traditionell_value: '1200 st/år'
        modern_value: Fritt
        type: row
        enabled: true
      - id: cmp_row_transaktioner
        label: Transaktioner
        detail: '10 transaktioner/bolag/månad ingår: skicka e-faktura, ta emot e-faktura, dokumenttolkning. Därefter **3 kr/st** per typ.'
        forvaltning_value: —
        traditionell_value: '10 st/mån'
        modern_value: '10 st/mån'
        type: row
        enabled: true
pricing_faq:
  -
    id: xgzvthM_wYrukerIZTeKq
    question: 'Har ni något för vilande eller holdingbolag?'
    answer: |-
      Absolut!
      För bolag där varken Traditionell eller Modern passar har vi även det som vi kallar Förvaltning
    highlight_plan_title: Förvaltning
    highlight_plan_description: 'För bolag där varken Traditionell eller Modern passar — typiskt holding, vilande eller inaktiva bolag. Byrån hanterar allt själv, kunden har ingen åtkomst.'
    highlight_monthly_price: '20'
    highlight_verification_limit: '50 st/år'
    highlight_included:
      - Bokföring
      - Försäljning
      - Inköp
      - Integrationer
    highlight_excluded:
      - Anläggningsregister
      - Periodisering
      - Avtalsfakturering
      - 'Bank & Skatt'
      - Lön
      - Månadsavslut
      - 'Attest & Betalning'
      - Automationer
      - Bankkoppling
      - Kickback
    type: faq
    enabled: true
users_small_title: Användarnivåer
users_title: "Fyra nivåer för kunden.\LOch alltid gratis för revisorn."
users_text: 'Byrån avgör vilka nivåer varje kund får tillgång till. Enkel och tydligt både för dig och dina kunder.'
user_levels:
  - id: mN1_MiniUserLevel001
    user_title: Mini
    user_description: 'App-användare: Skicka in underlag, aktivera bankkoppling och ta emot lönebesked.'
    type: levels
    enabled: true
    user_price: '0'
  - id: hwGLoSApfQOTP4KdFahxM
    user_title: 'Enkel användare'
    user_description: 'Allt från Mini + signera betalning, attestera, fakturera.'
    type: levels
    enabled: true
    user_price: '50'
  - id: 2TLxPzv7EOJUmVsrRoSAy
    user_title: 'Avancerad användare'
    user_description: 'Har tillgång till allt byrån ger tillgång till – full systemåtkomst om så önskas.'
    type: levels
    enabled: true
    user_price: '100'
  - id: lseZKWxepojbhh9ILASzX
    user_title: Läsbarhet
    user_description: 'Kan se allt, kan inte göra något — löpande åtkomst.'
    type: levels
    enabled: true
    user_price: '25'
  - id: BRlEzE1444k12P8xxvM7s
    user_title: Revisor
    user_description: 'Har tidsbegränsad åtkomst. Passar vid t.ex. bokslut — och gäller över alla bolagslägen.'
    type: levels
    enabled: true
    user_price: '0'
included_small_title: 'Löpande avgifter & transaktioner'
included_title: 'Och så det du använder. Inget annat.'
included_text: 'Transaktioner har en bundle på 10 st per bolag som ingår i månadspriset. Därefter prissätts varje enhet. Lönespec och extra bankkonton ligger utanför bundeln.'
included_columns:
  -
    id: CyBABpca8ZD_uwhb1Gt0V
    title: Löpande
    items:
      - 'Extra bankkonto 30 kr/konto/mån'
    type: columns
    enabled: true
  -
    id: KV7SOy1OTTtLbbR8GaNJW
    title: Transaktioner
    items:
      - 'Skicka e-faktura 3 kr/st'
      - 'Ta emot e-faktura 3 kr/st'
      - 'Dokumenttolkning 3 kr/st'
    type: columns
    enabled: true
  -
    id: xlw7eLNoe7j4K95V6pCAP
    title: Pay-as-you-go
    items:
      - 'Lönespecifikation 20 kr/st'
    type: columns
    enabled: true
text_small_title: Fakturering
text_title: "Hur och när\Lvi fakturerar."
text_body: |-
  Byrån styr vem som får fakturan för varje kostnadstyp på byrånivå med möjlighet att justera per bolag.

  Inställningarna kan enkelt anpassas efter respektive bolags behov.
notice_text: '<strong>Priserna i denna modell gäller vid helårsbetalning.</strong> Under uppstartsperioden fakturerar vi dock månadsvis. Transaktioner faktureras alltid månadsvis, oavsett avtalsform.'
routing_text: 'Tre delar kan styras- På byrånivå bestämmer ni vem som får fakturan för respektive kostnadstyp — byrån eller slutkunden.'
replicator_field:
  - id: pJ_DVRY74lIgZvIxZD6d9
    routing_label: test
    routing_title: testetste
    routing_text: tuaygaygdiausdoasudhoaushdo
    routing_note: asodhiahsdiaushd
    type: new_set
    enabled: true
routing_items:
  - id: un9KC3V6gtyR5S_jz8bIQ
    routing_label: Företagskostnaden
    routing_title: 'Månadspriset för bolagslägets abonnemang.'
    routing_note: 'T.ex. 125 kr/mån för Traditionell, 250 kr/mån för Modern.'
    type: new_set
    enabled: true
  - id: nROPQHbyimO0rmt81m1lG
    routing_label: Användarkostnaden
    routing_text: 'Avgiften per användare i Traditionell och Modern.'
    routing_note: 'T.ex. Enkel 50 kr/anv/mån, Avancerad 100 kr/anv/mån.'
    type: new_set
    enabled: true
    routing_title: 'Avgiften per användare i Traditionell och Modern.'
  - id: fyLMKhce0Dpr3U4tgLkpb
    routing_label: Transaktionerna
    routing_text: 'E-fakturor och dokumenttolkning utöver de 10 som ingår.'
    routing_note: '3 kr/st · faktureras alltid månadsvis.'
    type: new_set
    enabled: true
    routing_title: 'E-fakturor och dokumenttolkning utöver de 10 som ingår.'
routing_title: Routing-inställningar
cta_title: 'Trött på att betala premium för något som känns utdaterat? Boka demo eller logga in.'
cta_button_1_text: 'Boka demo'
cta_button_1_url: /boka-demo
cta_button_2_text: 'Logga in'
cta_button_2_url: 'https://app.oqto.se'
---
…för Oqto säljs inte till slutkund. Du som byrå är vår kund. Dina kunder är dina kunder. Du väljer läge per bolag, bestämmer användarnivåer och styr fakturering. Priset följer läget — storleken regleras naturligt via antal användare.