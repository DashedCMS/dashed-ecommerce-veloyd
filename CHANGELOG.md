# Changelog

All notable changes to `dashed-ecommerce-veloyd` will be documented in this file.

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
