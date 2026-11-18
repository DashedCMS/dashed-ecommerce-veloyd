# Changelog

All notable changes to `dashed-ecommerce-veloyd` will be documented in this file.

## Unreleased

<!-- TODO bij release: composer-constraint op dashed/dashed-ecommerce-core ophogen naar de versie met Classes\ParcelType (Veloyd werkt ook zonder, dan zonder verzendvorm). -->

### Added
- Verzendvorm (ec-core `ParcelType`): Veloyd registreert de productgroep-berekening (`veloyd-package-type`, inclusief de drempel per regio) met het standaard pakkettype van het land als terugval. Bestellingen zonder label krijgen zo in het CMS en de app een verzendvorm in plaats van "ONBEKEND". Alleen voor sites met een Veloyd API key; sites zonder Veloyd krijgen van dit pakket geen verzendvorm.
- Het labeloverzicht bij een bestelling toont per label groot en in kleur het pakkettype (PAKKET, BRIEVENBUS, BRIEF, ...), met dezelfde namen en kleuren als de verzendvorm in ec-core.

### Changed
- Klaarzetten voor Veloyd (`connectOrderWithCarrier`) volgt de verzendvorm van de order, zodat een override van de webshop (bijvoorbeeld een brievenbusregel) voorgaat op de productgroepen.
- Een label zonder gekozen pakkettype (app, automatisering, `createLabelForOrder`) en het formulier "Verzendlabel aanmaken" zonder klaargezet label gebruiken de verzendvorm van de order in plaats van altijd het standaard pakkettype van het land.
- Een productgroep zonder eigen Veloyd-pakkettype breekt de berekening niet meer af. Een leeg pakkettype op een productgroep telt nu als het standaard pakkettype van het land (voorheen telde alleen een ontbrekende waarde zo).

### Tests
- `tests/Unit` draait los (`vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit`). `tests/Integration` draait in de testharnas van dashed-ecommerce-core; het commando staat in `tests/Integration/bootstrap.php`.

## v4.18.0 - 2026-11-17

### Added
- Een mislukte labelaanvraag bij Veloyd slaat nu de echte foutmelding van de vervoerder op bij de zending, zodat die in het CMS en de app zichtbaar is. Na een gelukt label wordt de fout weer gewist.
- `Veloyd::replaceLabel()` vervangt een onbruikbaar label: het oude record en de track & trace worden verwijderd en er wordt met dezelfde instellingen een nieuw label aangemaakt.
- `Veloyd::deleteLabelRecord()` is nu de gedeelde plek voor het verwijderen van een label (gebruikt door het ordersscherm).

## v4.17.0 - 2026-11-16

### Changed
- Het taakcentrum (het tabje Bezig) toont nu het aanmaken van Veloyd-labels als één taak met voortgang per bestelling.

## v4.16.0 - 2026-11-13

### Added
- Orderlogboek: elk Veloyd-label komt in het logboek van de bestelling: aanmaken (CMS, app, automatisering en de concepten-cron, één regel per label), downloaden (losse download en "Download Veloyd labels", op naam van wie klikte), opnieuw klaarzetten, verwijderen (met de track & trace) en een geslaagd retourlabel (ook automatisch na een goedgekeurde retour en vanuit de app). Tags `order.label.created`, `order.label.downloaded`, `order.label.requeued`, `order.label.deleted`, `order.return-label.created`, altijd met een Nederlandse notitie (vervoerder, pakkettype, track & trace). Zonder ingelogde gebruiker is het een systeemregel; een fout bij het loggen breekt het label nooit.

## v4.15.3 - 2026-11-06

### Changed
- Geplande taken starten op een vast eigen tijdstip per site (`ScheduleSpread`), met de oude tijd op een oudere core.

## v4.15.2 - 2026-11-06

### Changed
- Statuscontroles draaien alleen met open zendingen. Een zending zonder bestelling breekt de run niet meer af. Backfill filtert in SQL.

## v4.15.1 - 2026-11-06

### Changed
- `dashed:create-veloyd-concept-orders` start alleen nog een eigen artisan-proces als er een order wacht.

## v4.14.0 - 2026-09-17

### Changed
- Geen Veloyd-retourlabel als een retour vanuit het CMS zonder klantmelding is aangemeld (`OrderReturnApprovedEvent::$notifyCustomer` uit dashed-ecommerce-core v4.129.0); op een oudere ec-core zonder die vlag blijft het gedrag ongewijzigd.

## v4.13.0 - 2026-09-10

### Added
- Optioneel veld "Status na aanmaken" in de Veloyd-labelactie op de bestelling: zet de bestelling direct na een geslaagd label door naar bijvoorbeeld Ingepakt, zodat er geen losse statuswijziging nodig is.

### Fixed
- `dashed:check-veloyd-orders` telt na een terugzet van afgehandeld naar open alleen labels mee die na die terugzet zijn aangemaakt (`Order::sinceFulfillmentReopened()`, dashed-ecommerce-core v4.119.0), ook voor de tijd-fallback `veloyd_auto_handled_after_shipped_days`. Eerder zetten de al bezorgde oude pakketten de bestelling bij de eerstvolgende run meteen weer op afgehandeld, nog voor er een nieuw label was; nu gebeurt dat pas als de nieuwe zending ook bezorgd is.

## 1.0.0 - 2026-05-13

- Initial release. Mirrors the MyParcel integration: concept creation, label PDF
  download, status sync, return labels, summary contributor and Filament settings.
