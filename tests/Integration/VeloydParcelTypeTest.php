<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceVeloyd\Tests\Integration;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Tests\TestCase;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Dashed\DashedEcommerceCore\Classes\ParcelType;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;

/**
 * Veloyd + ec-core ParcelType together. This package has no app harness of its
 * own, so these tests run inside the dashed-ecommerce-core test harness; see
 * tests/Integration/bootstrap.php for the command.
 */
class VeloydParcelTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ParcelType::reset();
        Veloyd::flushApiKeyCache();

        // CREATE TEMPORARY TABLE does not commit the RefreshDatabase transaction.
        Schema::create('dashed__order_veloyd', function (Blueprint $table) {
            $table->temporary();
            $table->id();
            $table->foreignId('order_id');
            $table->string('shipment_id')->nullable();
            $table->text('label')->nullable();
            $table->string('label_url')->nullable();
            $table->json('track_and_trace')->nullable();
            $table->boolean('label_printed')->default(0);
            $table->string('carrier')->nullable();
            $table->string('package_type')->nullable();
            $table->string('delivery_type')->nullable();
            $table->json('options')->nullable();
            $table->text('error')->nullable();
            $table->boolean('is_return')->default(0);
            $table->boolean('is_label_email_sent')->default(0);
            $table->text('personal_note')->nullable();
            $table->string('label_pdf_path')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Order::addDynamicRelation('veloydOrders', fn (Order $model) => $model->hasMany(VeloydOrder::class));

        // Same registration as DashedEcommerceVeloydServiceProvider::bootingPackage().
        ParcelType::computeUsing(fn (Order $order): ?int => Veloyd::computeParcelTypeCode($order));

        Customsetting::set('veloyd_api_key', 'test-key', 'default');
        Customsetting::set('veloyd_default_package_type_NL', 2, 'default');
    }

    public function test_computes_from_product_groups_with_country_default_and_threshold(): void
    {
        $order = $this->order();
        $this->line($order, 2);
        $this->line($order, null);
        $this->assertSame(ParcelType::MAILBOX, ParcelType::forOrder($order->fresh())['key']);

        $mixed = $this->order();
        $this->line($mixed, 2);
        $this->line($mixed, 1);
        $this->assertSame(1, ParcelType::carrierCodeFor($mixed->fresh()));

        // No product lines: the country default.
        $this->assertSame(2, ParcelType::carrierCodeFor($this->order()));

        Customsetting::set('veloyd_minimum_product_count_NL', 2, 'default');
        Customsetting::set('veloyd_minimum_product_count_package_type_NL', 1, 'default');
        ParcelType::flush();
        $this->assertSame(1, ParcelType::carrierCodeFor($order->fresh()));
    }

    public function test_site_without_api_key_gets_nothing_from_veloyd(): void
    {
        Customsetting::set('veloyd_api_key', '', 'default');
        Veloyd::flushApiKeyCache();

        $order = $this->order();
        $this->line($order, 2);

        $this->assertNull(Veloyd::computeParcelTypeCode($order->fresh()));
        $this->assertSame(ParcelType::UNKNOWN, ParcelType::forOrder($order->fresh())['key']);
    }

    public function test_connect_order_follows_the_override_and_otherwise_the_product_groups(): void
    {
        Http::fake(['*' => Http::response(['options' => []], 200)]);

        $plain = $this->order();
        $this->line($plain, 1);
        $this->line($plain, 2);
        Veloyd::connectOrderWithCarrier($plain->fresh());
        $this->assertSame(1, (int) VeloydOrder::where('order_id', $plain->id)->value('package_type'));
        $this->assertSame('PAKKET', ParcelType::forOrder($plain->fresh())['label']);

        ParcelType::overrideUsing(fn (Order $order) => ParcelType::MAILBOX);
        $overridden = $this->order();
        $this->line($overridden, 1);
        Veloyd::connectOrderWithCarrier($overridden->fresh());
        $this->assertSame(2, (int) VeloydOrder::where('order_id', $overridden->id)->value('package_type'));
    }

    public function test_create_label_defaults_to_the_parcel_type_and_explicit_choice_wins(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        ParcelType::overrideUsing(fn (Order $order) => 2);
        Customsetting::set('veloyd_default_package_type_NL', 1);

        $order = $this->order();
        $this->line($order, 1);

        rescue(fn () => Veloyd::createLabelForOrder($order->fresh()), null, false);
        $this->assertSame(2, (int) VeloydOrder::where('order_id', $order->id)->value('package_type'));

        rescue(fn () => Veloyd::createLabelForOrder($order->fresh(), ['package_type' => 1]), null, false);
        $this->assertSame(1, VeloydOrder::where('order_id', $order->id)->count());
        $this->assertSame(1, (int) VeloydOrder::where('order_id', $order->id)->value('package_type'));
    }

    private function order(): Order
    {
        return Order::create([
            'site_id' => 'default',
            'email' => 'klant@example.com',
            'invoice_id' => 'INV-V-' . Str::random(6),
            'status' => 'paid',
            'country' => 'NL',
            'hash' => Str::random(32),
        ]);
    }

    private function line(Order $order, ?int $groupPackageType): void
    {
        $group = ProductGroup::withoutEvents(fn () => ProductGroup::create([
            'name' => ['en' => 'Groep'], 'slug' => ['en' => 'groep-' . Str::random(6)], 'site_ids' => ['default'],
        ]));

        if ($groupPackageType !== null) {
            // `blocks` is translatable: key by the current locale.
            $group->customBlocks()->create(['blocks' => [app()->getLocale() => ['veloyd-package-type' => $groupPackageType]]]);
        }

        $product = Product::withoutEvents(fn () => Product::create([
            'name' => ['en' => 'Product'], 'slug' => ['en' => 'product-' . Str::random(6)], 'site_ids' => ['default'],
            'product_group_id' => $group->id, 'price' => 10, 'current_price' => 10,
        ]));

        OrderProduct::withoutEvents(fn () => OrderProduct::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'name' => 'Product', 'quantity' => 1, 'price' => 10,
        ]));
    }
}
