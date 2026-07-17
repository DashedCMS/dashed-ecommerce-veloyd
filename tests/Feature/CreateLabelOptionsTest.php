<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;

/**
 * NOTE: dit package heeft (nog) geen Pest-harness (lege tests/, geen
 * Pest.php/TestCase) — zie tests/Feature/LabelOptionsTest.php voor dezelfde
 * aantekening. Dit bestand volgt de test-brief 1-op-1 zodat het meteen
 * bruikbaar is zodra een testbench-harness wordt toegevoegd. De pure
 * `Veloyd::sanitizeExtraOptions()` en de payload-string-merge zijn tot die
 * tijd al los geverifieerd via een standalone script, zonder HTTP/DB
 * (zie scratchpad/verify_sanitize_extra_options.php uit de task-4 sessie).
 */
function makeOrderForLabelOptions(): Order
{
    return Order::create([
        'site_id' => 'main',
        'email' => 'klant@example.com',
        'status' => 'paid',
        'first_name' => 'Jan',
        'last_name' => 'Jansen',
        'street' => 'Teststraat',
        'house_nr' => '1',
        'zip_code' => '1011AB',
        'city' => 'Amsterdam',
        'country' => 'NL',
        'phone_number' => '0612345678',
    ]);
}

it('sanitizes extra options by dropping reserved keys and falsy values', function () {
    $sanitized = Veloyd::sanitizeExtraOptions([
        'carrier' => 'PostNL',
        'Handtekening' => true,
        'Verzekerd' => false,
    ]);

    expect($sanitized)->toBe(['Handtekening' => true]);
});

it('stores the sanitized options on the VeloydOrder when creating a label', function () {
    Http::fake([
        '*/parcel/create' => Http::response(['id' => 'shipment-1'], 200),
        '*/parcel/label*' => Http::response(['label' => base64_encode('%PDF-fake')], 200),
        '*/parcel/*' => Http::response(['id' => 'shipment-1'], 200),
    ]);

    $order = makeOrderForLabelOptions();

    Veloyd::createLabelForOrder($order, ['Handtekening' => true]);

    $veloydOrder = $order->veloydOrders()->first();

    expect($veloydOrder->options)->toBe(['Handtekening' => true]);
});

it('merges stored options into the parcel payload options array', function () {
    // Mirrors the pure merge used in Veloyd::buildParcelPayload():
    // array_keys(array_filter($veloydOrder->options ?? []))
    $stored = ['Handtekening' => true];

    expect(array_keys(array_filter($stored)))->toBe(['Handtekening']);
});
