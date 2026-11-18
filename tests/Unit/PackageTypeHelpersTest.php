<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceVeloyd\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;

/**
 * Pure package-type helpers behind the ParcelType computation and the label
 * defaults. Plain PHPUnit: this package has no app-booting test harness.
 */
class PackageTypeHelpersTest extends TestCase
{
    public function test_pick_biggest_keeps_the_existing_priority(): void
    {
        $this->assertSame(5, Veloyd::pickBiggestPackageType([2, 5, 1], null, null));
        $this->assertSame(4, Veloyd::pickBiggestPackageType([2, 4, 1], null, null));
        $this->assertSame(1, Veloyd::pickBiggestPackageType([2, 1, 6], null, null));
        $this->assertSame(6, Veloyd::pickBiggestPackageType([2, 6, 3], null, null));
        $this->assertSame(2, Veloyd::pickBiggestPackageType([2, 3], null, null));
        $this->assertSame(3, Veloyd::pickBiggestPackageType([3], null, null));
        $this->assertSame(3, Veloyd::pickBiggestPackageType([], null, null));
        $this->assertSame(2, Veloyd::pickBiggestPackageType(['2', '3'], null, null));
    }

    public function test_pick_biggest_applies_the_product_count_threshold(): void
    {
        $this->assertSame(1, Veloyd::pickBiggestPackageType([2, 2, 2], 3, 1));
        $this->assertSame(1, Veloyd::pickBiggestPackageType([2, 2, 2], '3', '1'));
        $this->assertSame(2, Veloyd::pickBiggestPackageType([2, 2], 3, 1));
        // Without a threshold package type the threshold is ignored.
        $this->assertSame(2, Veloyd::pickBiggestPackageType([2, 2, 2], 3, null));
    }

    public function test_group_types_fall_back_to_the_country_default(): void
    {
        // A group without its own type counts as the country default.
        $this->assertSame(1, Veloyd::packageTypeFromGroupTypes([2, null], 1, null, null));
        $this->assertSame(2, Veloyd::packageTypeFromGroupTypes([2, ''], '2', null, null));
        $this->assertSame(2, Veloyd::packageTypeFromGroupTypes(['2', '2'], 1, null, null));
        $this->assertSame(1, Veloyd::packageTypeFromGroupTypes([2, 2, 2], 1, 3, 1));
    }

    public function test_order_without_product_lines_gets_the_country_default_or_null(): void
    {
        $this->assertSame(2, Veloyd::packageTypeFromGroupTypes([], '2', 3, 1));
        $this->assertNull(Veloyd::packageTypeFromGroupTypes([], null, 3, 1));
        $this->assertNull(Veloyd::packageTypeFromGroupTypes([], '', null, null));
    }

    public function test_label_default_prefers_the_parcel_type_code(): void
    {
        $this->assertSame(2, Veloyd::defaultPackageTypeFromCode(2, 1));
        $this->assertSame(6, Veloyd::defaultPackageTypeFromCode(null, '6'));
        $this->assertSame(1, Veloyd::defaultPackageTypeFromCode(null, null));
    }
}
