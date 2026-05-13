<?php

namespace Dashed\DashedEcommerceVeloyd;

use Filament\Panel;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;
use Dashed\DashedEcommerceVeloyd\Jobs\CreateShippingLabelsJob;
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
                    ->label('Veloyd pakket type')
                    ->options(Veloyd::getPackageTypes()),
            ]);
    }

    public function boot(Panel $panel): void
    {
        cms()->builder('builderBlockClasses', [
            self::class => 'builderBlocks',
        ]);

        if (VeloydOrder::where('label_printed', 0)->whereNotNull('shipment_id')->count()) {
            ecommerce()->buttonActions(
                'orders',
                array_merge(ecommerce()->buttonActions('orders'), [
                    Action::make('downloadVeloydLabels')
                        ->button()
                        ->label('Download Veloyd Labels (' . VeloydOrder::where('label_printed', 0)->whereNotNull('shipment_id')->count() . ')')
                        ->openUrlInNewTab()
                        ->action(function () {
                            CreateShippingLabelsJob::dispatch(auth()->user())->onQueue('ecommerce');

                            Notification::make()
                                ->body('Labels worden aangemaakt, ze staan over een paar minuten klaar om te downloaden')
                                ->success()
                                ->send();
                        }),
                ])
            );
        }

        // Handmatig de periodieke Veloyd-status-sync triggeren voor alle
        // niet-afgehandelde bestellingen. De command draait normaal elk
        // kwartier via de scheduler; deze knop dispatcht hem direct naar
        // de queue zodat de admin niet hoeft te wachten op de volgende run.
        ecommerce()->buttonActions(
            'orders',
            array_merge(ecommerce()->buttonActions('orders'), [
                Action::make('syncVeloydStatuses')
                    ->iconButton()
                    ->color('gray')
                    ->icon('heroicon-o-arrow-path')
                    ->label('Verzendstatussen ophalen bij Veloyd')
                    ->tooltip('Verzendstatussen ophalen bij Veloyd')
                    ->requiresConfirmation()
                    ->modalHeading('Verzendstatussen synchroniseren')
                    ->modalDescription('Hiermee wordt voor elke niet-afgehandelde bestelling de huidige status bij Veloyd opgehaald en bijgewerkt. De sync draait in de achtergrond.')
                    ->modalSubmitActionLabel('Sync starten')
                    ->action(function () {
                        Artisan::queue('dashed:check-veloyd-orders')->onQueue('ecommerce');

                        Notification::make()
                            ->title('Sync gestart')
                            ->body('De verzendstatussen worden in de achtergrond opgehaald bij Veloyd.')
                            ->success()
                            ->send();
                    }),
            ])
        );
    }
}
