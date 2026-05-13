<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceVeloyd\Services\Summary;

use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;
use Dashed\DashedCore\Services\Summary\DTOs\SummaryPeriod;
use Dashed\DashedCore\Services\Summary\DTOs\SummarySection;
use Dashed\DashedCore\Services\Summary\Contracts\SummaryContributorInterface;

/**
 * Samenvatting-bijdrage voor Veloyd. Toont in de mail het aantal
 * verzendlabels, retourlabels en gemailde retourlabels die in de
 * periode zijn aangemaakt.
 */
class VeloydSummaryContributor implements SummaryContributorInterface
{
    public static function key(): string
    {
        return 'veloyd';
    }

    public static function label(): string
    {
        return 'Veloyd';
    }

    public static function description(): string
    {
        return 'Aangemaakte verzendlabels en retourlabels in de periode.';
    }

    public static function defaultFrequency(): string
    {
        return 'weekly';
    }

    public static function availableFrequencies(): array
    {
        return ['daily', 'weekly', 'monthly'];
    }

    public static function contribute(SummaryPeriod $period): ?SummarySection
    {
        $shippingLabels = VeloydOrder::query()
            ->where('is_return', false)
            ->where('label_printed', true)
            ->whereBetween('updated_at', [$period->start, $period->end])
            ->count();

        $returnLabels = VeloydOrder::query()
            ->where('is_return', true)
            ->where('label_printed', true)
            ->whereBetween('updated_at', [$period->start, $period->end])
            ->count();

        $returnLabelsMailed = VeloydOrder::query()
            ->where('is_return', true)
            ->where('is_label_email_sent', true)
            ->whereBetween('updated_at', [$period->start, $period->end])
            ->count();

        if ($shippingLabels === 0 && $returnLabels === 0 && $returnLabelsMailed === 0) {
            return null;
        }

        $stats = [
            ['label' => 'Aangemaakte verzendlabels', 'value' => (string) $shippingLabels],
            ['label' => 'Aangemaakte retourlabels', 'value' => (string) $returnLabels],
            ['label' => 'Retourlabels gemaild naar klant', 'value' => (string) $returnLabelsMailed],
        ];

        $blocks = [
            ['type' => 'stats', 'data' => ['rows' => $stats]],
        ];

        return new SummarySection(
            title: 'Veloyd',
            blocks: $blocks,
        );
    }
}
