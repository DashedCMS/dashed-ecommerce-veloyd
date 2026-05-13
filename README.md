# Dashed Ecommerce Veloyd

Veloyd shipping integration for the Dashed CMS. Mirrors the MyParcel integration:
automatic concept creation for paid orders, label generation (PDF), track & trace
sync, and return labels.

## Installation

```bash
composer require dashed/dashed-ecommerce-veloyd
php artisan migrate
```

## Configuration

Open the admin panel and navigate to **Settings → Veloyd**. Enter the API key
from your Veloyd account (https://app.veloyd.nl) and configure per-region defaults
for carrier, package type and delivery type.

## Usage

Once configured, paid orders are automatically pushed to Veloyd as concepts.
Use the admin order overview to download labels in bulk or per-order. Return
labels can be generated from the order sidebar.

## License

MIT
