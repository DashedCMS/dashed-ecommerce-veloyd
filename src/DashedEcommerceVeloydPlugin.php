<?php

namespace Dashed\DashedEcommerceVeloyd;

use Filament\Panel;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;
use Dashed\DashedEcommerceVeloyd\Jobs\CreateShippingLabelsJob;
use Dashed\DashedEcommerceVeloyd\Support\VeloydShippingProvider;
use Dashed\DashedEcommerceVeloyd\Filament\Pages\Settings\VeloydSettingsPage;

class DashedEcommerceVeloydPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-ecommerce-veloyd';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                VeloydSettingsPage::class,
            ]);
    }

    public static function builderBlocks(): void
    {
        cms()
            ->builder('productGroupBlocks', [
                Select::make('veloyd-package-type')
                    ->label(__('Veloyd pakket type'))
                    ->options(Veloyd::getPackageTypes()),
            ]);
    }

    public function boot(Panel $panel): void
    {
        cms()->builder('builderBlockClasses', [
            self::class => 'builderBlocks',
        ]);

        ecommerce()->registerShippingLabelProvider(new VeloydShippingProvider());

        if (VeloydOrder::where('label_printed', 0)->whereNotNull('shipment_id')->count()) {
            ecommerce()->buttonActions(
                'orders',
                array_merge(ecommerce()->buttonActions('orders'), [
                    Action::make('downloadVeloydLabels')
                        ->button()
                        ->label(__('Download Veloyd Labels (:aantal)', ['aantal' => VeloydOrder::where('label_printed', 0)->whereNotNull('shipment_id')->count()]))
                        ->openUrlInNewTab()
                        ->action(function () {
                            CreateShippingLabelsJob::dispatch(auth()->user())->onQueue('ecommerce');

                            Notification::make()
                                ->body(__('Labels worden aangemaakt, ze staan over een paar minuten klaar om te downloaden'))
                                ->success()
                                ->send();
                        }),
                ])
            );
        }

        ecommerce()->registerShippingStatusCommand('dashed:check-veloyd-orders');
    }
}
