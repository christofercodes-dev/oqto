---
id: 32d9837c-b2b6-4e18-9de7-46f419672a26
blueprint: ekosystem
title: 'Hino + Reda + Oqto'
slug: hino-reda-oqto
order: 1
headline: 'Tre system. Ett flöde.'
short_description: 'Se hur Oqto, Reda och Hino pratar med varandra, från inkommande faktura till matchad betalning. Varje system gör det det är bäst på.'
systems:
  -
    key: reda
    name: Reda
    role: Fakturatolkning
    tone: blue
    integration: a0e04474-ffc2-419d-b7ee-8f712a46fa9a
    pos_x: 22
    pos_y: 22
  -
    key: hino
    name: Hino
    role: 'Föreningens system'
    tone: yellow
    integration: 83683315-06f6-4a04-a85b-ce03d50f4ead
    pos_x: 78
    pos_y: 22
  -
    key: oqto
    name: Oqto
    role: Ekonomisystem
    tone: peach
    icon: oqto
    pos_x: 54
    pos_y: 58
  -
    key: leverantor
    name: Leverantör
    role: 'Peppol · PDF · papper'
    tone: gray
    icon: supplier
    pos_x: 20
    pos_y: 84
  -
    key: bank
    name: Banken
    role: 'Open Banking'
    tone: gray
    icon: bank
    pos_x: 82
    pos_y: 84
flows:
  -
    id: flow-faktura-till-bokforing
    type: flow
    enabled: true
    title: 'Faktura till bokföring'
    steps:
      -
        system: reda
        with: leverantor
        title: 'Faktura in'
        short: 'Peppol, PDF eller papper'
        text: 'Fakturan kommer in till Reda som e-faktura via Peppol, som PDF eller på papper.'
        log: 'Mottagen som e-faktura via Peppol'
        status: Mottagen
      -
        system: reda
        title: Tolkning
        short: 'AI-tolkning på radnivå'
        text: 'Reda tolkar fakturan med AI på radnivå och konterar enligt föreningens regler.'
        log: 'Tolkad och konterad enligt regler'
        status: Tolkad
      -
        system: hino
        with: reda
        title: Attest
        short: 'Styrelsen attesterar'
        text: 'Styrelsen attesterar i Hino, kopplat via Redas API.'
        log: 'Attesterad av styrelsen i appen'
        status: Attesterad
      -
        system: oqto
        with: reda
        title: Bokföring
        short: 'Skapas i Oqto via API'
        text: 'Leverantörsfakturan skapas i Oqto via API och bokförs.'
        log: 'Leverantörsfaktura skapad via API'
        status: Bokförd
  -
    id: flow-betalning-till-resultat
    type: flow
    enabled: true
    title: 'Betalning till resultat'
    steps:
      -
        system: oqto
        title: 'Faktura in'
        short: 'Med dokumentet bifogat'
        text: 'Fakturan ligger i Oqto med dokumentet bifogat, redo att betalas.'
        log: 'Skapad i Oqto med dokument bifogat'
        status: Skapad
      -
        system: oqto
        with: bank
        title: Signering
        short: 'BankID i webb eller app'
        text: 'Behörig person signerar betalningen med BankID, i webben eller i Oqtos app.'
        log: 'Signerad med BankID'
        status: Signerad
      -
        system: bank
        with: oqto
        title: Återrapportering
        short: 'Banken rapporterar'
        text: 'Banken genomför betalningen och återrapporterar till Oqto, för förvaltarens del.'
        log: 'Genomförd och återrapporterad'
        status: Genomförd
      -
        system: oqto
        with: bank
        title: Matchning
        short: 'Markeras betald'
        text: 'Oqto matchar betalningen mot fakturan och markerar den som betald.'
        log: 'Matchad mot fakturan, betald'
        status: Matchad
      -
        system: hino
        with: oqto
        title: 'Resultat i Hino'
        short: 'Föreningen ser resultatet'
        text: 'Resultatet visas för föreningen i Hino, utan en enda manuell överföring.'
        log: 'Resultatet visas för föreningen'
        status: Betald
doc_label: 'Leverantörsfaktura 2026-0418'
doc_title: 'Fastighetsservice Norr AB'
doc_subtitle: 'Brf Linden · Trappstädning oktober'
doc_amount_label: 'Att betala'
doc_amount: '12 450 kr'
seo_title: 'Hino + Reda + Oqto: tre system, ett flöde'
seo_description: 'Se hur Oqto, Reda och Hino fungerar tillsammans, från inkommande faktura till matchad betalning.'
---
