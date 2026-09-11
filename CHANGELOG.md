# Changelog

All notable changes to `dashed-ecommerce-veloyd` will be documented in this file.

## v4.13.0 - 2026-09-10

### Added
- Optioneel veld "Status na aanmaken" in de Veloyd-labelactie op de bestelling: zet de bestelling direct na een geslaagd label door naar bijvoorbeeld Ingepakt, zodat er geen losse statuswijziging nodig is.

### Fixed
- `dashed:check-veloyd-orders` telt na een terugzet van afgehandeld naar open alleen labels mee die na die terugzet zijn aangemaakt (`Order::sinceFulfillmentReopened()`, dashed-ecommerce-core v4.119.0), ook voor de tijd-fallback `veloyd_auto_handled_after_shipped_days`. Eerder zetten de al bezorgde oude pakketten de bestelling bij de eerstvolgende run meteen weer op afgehandeld, nog voor er een nieuw label was; nu gebeurt dat pas als de nieuwe zending ook bezorgd is.

## 1.0.0 - 2026-05-13

- Initial release. Mirrors the MyParcel integration: concept creation, label PDF
  download, status sync, return labels, summary contributor and Filament settings.
