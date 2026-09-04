---
id: 03c890d5-ba59-469a-9151-dc0807f7f3f6
blueprint: pricing
title: "Phew…Skönt att \Ldu jobbar på redovisningsbyrå"
template: pricing
users:
  - 4dcd5e55-456c-4762-8d2f-b2b41cf595f2
updated_by: 4dcd5e55-456c-4762-8d2f-b2b41cf595f2
updated_at: 1787911261
pricing_button_text: 'Boka demo'
intro_small_title: Bolagslägen
intro_title: "Ingen byrålicens, \Lbara olika bolagslägen."
intro_body: 'Vi har ingen byrålicens och systemet kostar ingenting för er som redovisningsbyrå. Vi tar betalt per företag och användarna på dessa. Välj mellan Traditionell, Modern eller Förvaltning nedan.'
pricing_plans:
  -
    id: SOfAiZFnbi7K6ImrmAgJm
    plan_title: Traditionell
    plan_number: '01'
    description: 'I det traditionella läger arbetar byrå och kund tillsammans utan månadsavslut och automationer. Passar bolag med mindre transaktionsvolym.'
    monthly_price: '125'
    verification_limit: '100 st / mån'
    type: pricing_plan
    enabled: true
    features:
      - Bokföring
      - Försäljning
      - Inköp
      - Anläggningsregister
      - Periodisering
      - Avtalsfakturering
      - 'Bank & Skatt'
      - Lön
      - 'Attest & Betalning'
  -
    id: eTiJRgHFQetkdFMafsa1S
    plan_title: Modern
    plan_number: '02'
    description: 'Byrå och kund arbetar tillsammans. med månadsavslut och automationer. Passar volah med hög transaktionsvolym.'
    features:
      - Bokföring
      - Försäljning
      - Inköp
      - Anläggningsregister
      - Periodisering
      - Avtalsfakturering
      - 'Bank & Skatt'
      - Lön
      - 'Attest & Betalning'
      - Månadsavslut
      - 'Automationer (Autopilot)'
    monthly_price: '250'
    verification_limit: Fritt!
    type: pricing_plan
    enabled: true
pricing_faq:
  - id: xgzvthM_wYrukerIZTeKq
    question: 'Har ni något för vilande eller holdingbolag?'
    answer: 'Ja. Oqto passar även för vilande bolag och holdingbolag. Vi har lösningar som gör det enkelt att hålla bolagets ekonomi och administration på en rimlig nivå även när verksamheten är begränsad.'
    type: faq
    enabled: true
users_small_title: Användarnivåer
users_title: "Tre nivåer för kunden.\LOch alltid gratis för revisorn."
users_text: "Byrån avgör vilka nivåer varje kund får tillgång till. En enkel användare kan signera betalning, attestera, fakturera, app-funktionalitet. En avancerad \Lanvändare kan göra exakt det byrån tillåter – inte mer. Läsbehöriga kan se allt, men inte göra något. Revisor har tidsbegränsad åtkomst. Passar vid t.ex. bokslut — och gäller över alla bolagslägen."
user_levels:
  - id: hwGLoSApfQOTP4KdFahxM
    user_title: 'Enkel användare'
    user_description: 'Signera betalning, attestera, fakturera, app-funktionalitet'
    type: levels
    enabled: true
    user_price: '50'
  - id: 2TLxPzv7EOJUmVsrRoSAy
    user_title: 'Avancerad användare'
    user_description: 'Allt byrån ger tillgång till – full systemåtkomst'
    type: levels
    enabled: true
    user_price: '100'
  - id: lseZKWxepojbhh9ILASzX
    user_title: Läsbarhet
    user_description: 'Kan se allt, kan inte göra något — löpande åtkomst'
    type: levels
    enabled: true
    user_price: '25'
  - id: BRlEzE1444k12P8xxvM7s
    user_title: Revisor
    user_description: 'Tidsbegränsad. Gäller alla bolagslägen.'
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
text_small_title: Test
text_title: "Hur och när\Lvi fakturerar."
text_body: |-
  Byrån styr vem som får fakturan för varje kostnadstyp på byrånivå med möjlighet att justera per bolag.

  Inställningarna kan enkelt anpassas efter respektive bolags behov.
notice_text: 'Priserna i denna modell gäller vid helårsbetalning. Under uppstartsperioden fakturerar vi dock månadsvis. Transaktioner faktureras alltid månadsvis, oavsett avtalsform.'
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
    routing_note: '.ex. Enkel 50 kr/anv/mån, Avancerad 100 kr/anv/mån.'
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
cta_title: "Trött på att betala premium \Lför något som känns utdaterat? \LBoka demo eller logga in."
cta_button_1_text: 'Boka demo'
cta_button_2_text: 'Logga in'
---
…för Oqto säljs inte till slutkund. Du som byrå är vår kund. Dina kunder är dina kunder. Du väljer läge per bolag, bestämmer användarnivåer och styr fakturering. Priset följer läget — storleken regleras naturligt via antal användare.