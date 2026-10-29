---
id: 6d9cce90-2ee1-4234-9898-291d417ec792
blueprint: integration
title: 'Stripe bokföring'
external_id: c211dd46-611d-48e1-ac9b-e3c68186f1fd
short_description: 'Stripe-köp, returer, avgifter och utbetalningar'
description: |-
  Stripe bokföring i Oqto automatiserar bokföringen av betalningar, returer, avgifter och utbetalningar från Stripe.

  För varje slutförd Stripe-betalning skapar integrationen en kundfaktura i Oqto och registrerar betalningen mot kontot för Stripe-fordran. Returer bokförs som separata kreditfakturor och Stripe-avgifterna samlas på utbetalningsverifikatet. Varje betalning går att följa för sig.

  Försäljningskonto och moms kan ställas in för Sverige, EU och export, och flera momssatser stöds när de finns angivna i Stripe. Det går också att bokföra all försäljning enligt ett svenskt upplägg, eller bara utbetalningar och avgifter om försäljningen redan hanteras i ett annat system.

  Börja i testläge och kontrollera underlaget innan något skapas i Oqto. Kör sedan ett mindre antal händelser manuellt, och slå på den automatiska bokföringen när allt ser bra ut.
reasons:
  - 'Varje Stripe-betalning blir en egen kundfaktura med registrerad betalning, så att du kan följa den i Oqto.'
  - 'Betalningar, returer och utbetalningar hanteras i samma flöde, från försäljning och retur till avstämd Stripe-utbetalning.'
  - 'Stripe-avgifterna bokförs automatiskt på utbetalningsverifikatet, med valfri kontering för omvänd moms.'
  - 'Du styr själv försäljningskonto och moms för Sverige, EU och export, eller kör allt enligt ett svenskt upplägg.'
  - 'Du kan börja i testläge och köra några händelser manuellt innan du slår på full automatik.'
developer_name: Integrati
company_employee_ranges:
  - '0'
  - 1-9
  - 10-49
company_revenue_ranges:
  - 1-3
  - 3-10
  - 10-50
partner_email: info@integrati.se
partner_phone: 070-0856525
partner_website: 'https://integrati.se/i/oqto-stripe-bokforing'
partner_about: |-
  Vi kopplar ihop systemen du använder. Vi bygger varje integration på riktigt. Vi tar den svåra vägen för att det blir bättre.

  Funderar du på något som borde gå att automatisera? Hör av dig, så får du ett ärligt svar om det är värt det eller inte.
price: 'Testa kostnadsfritt i 30 dagar. Därefter 299 kr/mån exkl. moms, utan startavgift.'
source_created_at: '2026-10-06 14:40'
avatar:
  - images/6ddb3105-957b-44f1-8bcf-77b49be8c76c.png
sni_codes:
  - '47'
  - '55'
---
