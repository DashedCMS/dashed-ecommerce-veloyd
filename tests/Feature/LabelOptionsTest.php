<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;

/**
 * NOTE: dit package heeft (nog) geen Pest-harness (lege tests/, geen
 * Pest.php/TestCase). Dit bestand volgt de test-brief 1-op-1 zodat het
 * meteen bruikbaar is zodra een testbench-harness wordt toegevoegd
 * (vergelijkbaar met dashed-ecommerce-core). De pure mapping-functies
 * (`optionStringsToFields`, `readOptionsForDisplay`) zijn tot die tijd al
 * los geverifieerd via een standalone script, zonder HTTP/DB.
 */
function makeOrder(): Order
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

it('builds extra options from the veloyd /parcel/options response', function () {
    Http::fake(['*/parcel/options' => Http::response(['options' => ['Handtekening', 'Verzekerd']], 200)]);

    $fields = Veloyd::extraLabelOptions(makeOrder());

    $names = array_column($fields, 'name');
    expect($names)->toContain('Handtekening', 'Verzekerd');
    expect(collect($fields)->firstWhere('name', 'Handtekening')['type'])->toBe('boolean');
});

it('falls back to a fixed list when the options call fails', function () {
    Http::fake(['*/parcel/options' => Http::response([], 500)]);

    $names = array_column(Veloyd::extraLabelOptions(makeOrder()), 'name');
    expect($names)->toContain('Retour');
});

it('maps stored veloyd options to a readable list', function () {
    $readable = Veloyd::readOptionsForDisplay(['Handtekening' => true]);

    expect($readable[0])->toMatchArray(['key' => 'Handtekening', 'value' => 'Ja']);
});

it('drops falsy/empty options from the readable list', function () {
    $readable = Veloyd::readOptionsForDisplay(['Handtekening' => true, 'Verzekerd' => false]);

    expect($readable)->toHaveCount(1);
    expect($readable[0]['key'])->toBe('Handtekening');
});
