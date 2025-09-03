# Ontwerpdocument Website & Intranet Stichting NederAI

## 1. Inleiding
Stichting NederAI is een Nederlandse stichting met zetel in de gemeente Wijchen.
Volgens artikel 2 van de statuten heeft de organisatie als doel het dienen van de publieke en strategische belangen van Nederland en Europa op het gebied van kunstmatige intelligentie, met nadruk op mensgerichte, duurzame en ethisch verantwoorde toepassingen. De Waardenverklaring (hoofdstuk 2) vormt het hoogste toetsingskader voor al het handelen en vereist transparantie, inclusiviteit en neutraliteit.

Dit document beschrijft het ontwerp voor een gecombineerde publieke website en administratief intranet waarmee de stichting haar missie ondersteunt en transparantie automatiseert.

## 2. Doelstellingen
* Communiceer de missie, waarden en activiteiten van Stichting NederAI naar het brede publiek.
* Faciliteer interne bestuurprocessen en documentbeheer voor de stichting en haar groepsvennootschappen (Alliance, Institute en Commercial).
* Ondersteun de flexibele organisatiestructuur van zelforganiserende cirkels en rolgebaseerde samenwerking.
* Automatiseer transparantie door openbare registers, publicaties en toegankelijkheid van besluiten en financiële rapportages.
* Bied een veilige omgeving voor interne samenwerking, besluitvorming en communicatie.

## 3. Doelgroepen
1. **Publiek en belanghebbenden** – burgers, overheden, partners en media die informatie zoeken over de stichting, projecten en beleidsstukken.
2. **Bestuur en medewerkers** – bestuurders en medewerkers van de stichting en haar vennootschappen die administratieve taken uitvoeren.
3. **Toezichthoudende organen** – nationale instanties die op grond van hoofdstuk 8 toezicht houden op naleving van statuten en Waardenverklaring.
4. **Klokkenluiders** – personen die vermoedelijke schendingen willen melden volgens artikel 35.

## 4. Functionele vereisten
### 4.1 Publieke website
* **Missie en Waarden** – overzicht van doelstelling (art. 2) en Waardenverklaring (hoofdstuk 2).
* **Organisatiestructuur** – visualisatie van netwerk van zelforganiserende cirkels, rollen en groepsvennootschappen (hoofdstuk 4) met diagrammen.
* **Nieuws & Projecten** – openbare informatie over onderzoek, maatschappelijke projecten en commerciële activiteiten.
* **Jaarrekening & Rapportages** – publicatie van jaarrekeningen en beleid (art. 37) in een documentbibliotheek.
* **Uitkeringenregister** – publiek inzicht in het register van uitkeringen (art. 36).
* **Open Data** – downloadbare datasets, onderzoeksresultaten en beleidsdocumenten.
* **Klokkenluidersloket** – beveiligd formulier voor meldingen, met keuze tussen interne melding en toezichthouder (art. 35).

### 4.2 Administratief intranet
* **Authenticatie en rollen** – inloggen via tweefactorauthenticatie met rolgebaseerde toegang (bestuur, medewerkers, toezichthouder).
* **Cirkels & Rollenbeheer** – registreren van zelforganiserende cirkels, toewijzing van rollen en bijhouden van rolportfolio's.
* **Besluitvorming** – module voor agendabeheer, notulen, stemming en vastlegging van besluiten (art. 17). Besluiten worden na definitieve goedkeuring automatisch gepubliceerd op de publieke site.
* **Projectteamvorming** – ondersteuning voor ad hoc samenstelling van multidisciplinaire teams op basis van rollen en expertise.
* **Documentbeheer** – versiebeheer van beleidsstukken, contracten en reglementen met tags voor relevante statutaire artikelen.
* **Financieel beheer** – invoer en goedkeuring van financiële transacties; automatische synchronisatie naar het uitkeringenregister en jaarrekeningmodules (art. 24–27, 36, 37).
* **Feedback & Leren** – faciliteiten voor retrospectives, rollenoverleg en 360-graden feedback.
* **Samenwerkingscontracten** – workflow voor missietoets en vastlegging van samenwerkingen (hoofdstuk 6), inclusief toetsingsverslag en besluit.
* **Klokkenluidersbeheer** – ontvang, registreer en volg meldingen; mogelijkheid tot doorgeleiding naar toezichthouder (art. 35).
* **Audittrail & logging** – volledige logging van acties voor toetsing (art. 34) en intern onderzoek.

## 5. Informatiearchitectuur
### 5.1 Navigatiestructuur publieke website
1. Home
2. Missie & Waarden
3. Organisatie
   * Bestuur
   * Groepsstructuur
   * Cirkels & Rollen
4. Projecten & Nieuws
5. Documenten
   * Statuten & reglementen
   * Jaarrekeningen
   * Uitkeringenregister
6. Samenwerken
7. Klokkenluiders
8. Contact

### 5.2 Navigatiestructuur intranet
1. Dashboard
2. Cirkels & Rollen
3. Vergaderingen & Besluiten
4. Projectteams
5. Documentbeheer
6. Financiën
7. Samenwerkingen
8. Klokkenluidersbeheer
9. Rapportages
10. Beheer (rollen, instellingen)

## 6. Technische architectuur
* **Front-end** – responsieve webinterface met een open-source framework (bijv. React of Vue). Publieke site kan deels statisch worden gegenereerd voor prestaties en toegankelijkheid.
* **Back-end** – API‑gedreven architectuur (bijv. Node.js of Python/Django) met gescheiden omgevingen voor publieke en interne functionaliteit.
* **Database** – relationele database (PostgreSQL) voor structuur en audittrail; versleutelde opslag van gevoelige gegevens.
* **Authenticatie** – OAuth2/OpenID Connect met multi-factor, integratie met mogelijk bestaande identiteitsproviders.
* **Hosting** – Nederlandse of Europese cloudprovider die voldoet aan Europese privacywetgeving; infrastructuur as code voor reproduceerbaarheid.
* **Open source** – broncode en documentatie worden publiek beschikbaar gemaakt waar mogelijk, conform waarden van open samenwerking.

## 7. Beveiliging en privacy
* HTTPS en HSTS voor alle verkeer.
* Regelmatige beveiligingsaudits en penetratietesten.
* Logische scheiding tussen publieke en interne systemen; strikte toegang op basis van rollen.
* Dataminimalisatie en versleuteling conform AVG.
* Back-ups, monitoring en incidentresponseprocedures.

## 8. Transparantie en rapportage
* Automatische publicatie van goedgekeurde besluiten, jaarrekeningen, het uitkeringenregister en samenwerkingsverslagen.
* Dashboard voor toezichthoudende organen met realtime toegang tot relevante documenten (art. 34).
* Verslaglegging over toepassing van Waardenverklaring in het beleid (art. 37 lid 6).

## 9. Governance en beheer
* **Rolmodel**: bestuurders, medewerkers, auditors en toezichthouders met duidelijke bevoegdheden (artikelen 10–16).
* **Cirkels & rolportfolio's** – transparant beheer van cirkels, rollen en individuele rolportfolio's.
* **Workflowregels** conform missietoets en subsidiariteitsbeginsel (hoofdstuk 6, art. 31).
* **Archivering** – bewaartermijnen van ten minste zeven jaar voor registers en financiële documenten (art. 36).
* **Verantwoordingsmechanismen** – mogelijkheid voor externe toetsing en export van auditlogs.

## 10. UX-richtlijnen
* Toegankelijkheid volgens WCAG 2.1 niveau AA.
* Eenduidige terminologie die de waarden van de stichting reflecteert.
* Heldere scheiding tussen publieke content en intranetfunctionaliteit, maar consistente huisstijl.

## 11. Implementatie & roadmap
1. **Fase 1 – Basispublicatie**: statische publieke site met missie, waarden, organisatiestructuur en documentbibliotheek.
2. **Fase 2 – Intranetkern**: authenticatie, besluitvorming, documentbeheer en financieel register.
3. **Fase 3 – Transparantieautomatisering**: automatische publicaties, klokkenluidersloket en dashboards voor toezicht.
4. **Fase 4 – Optimalisatie**: integratie met externe systemen, open data API's en uitbreiding van samenwerkingstools.

## 12. Onderhoud en evaluatie
* Halfjaarlijkse evaluatie van functionaliteiten en naleving van Waardenverklaring.
* Gebruik van issue‑tracking en publieke roadmap voor voortdurende verbetering.
