<?php

namespace Dashed\DashedEcommerceVeloyd\Livewire\Orders;

use Throwable;
use Livewire\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class ShowPushToVeloydOrder extends Component implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    public Order $order;

    public function mount(Order $order)
    {
        $this->order = $order;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }

    public function action(): Action
    {
        return Action::make('action')
            ->label('Verzendlabel aanmaken')
            ->color('primary')
            ->icon('heroicon-o-document-arrow-down')
            ->fillForm(function () {
                $data = [];

                $veloydOrder = $this->order->veloydOrders()->where('label_printed', 0)->first();

                $data['package_type'] = $veloydOrder->package_type ?? Customsetting::get("veloyd_default_package_type_{$this->order->countryIsoCode}", null, 1);
                $data['delivery_type'] = $veloydOrder->delivery_type ?? Customsetting::get("veloyd_default_delivery_type_{$this->order->countryIsoCode}", null, 'Standaard');
                $data['carrier'] = $veloydOrder->carrier ?? Customsetting::get("veloyd_default_carrier_{$this->order->countryIsoCode}", null, 'PostNL');

                return $data;
            })
            ->schema(function () {
                return [
                    Select::make("carrier")
                        ->label('Carrier')
                        ->required()
                        ->options(Veloyd::getCarriers()),
                    Select::make("package_type")
                        ->label('Pakket type')
                        ->required()
                        ->options(Veloyd::getPackageTypes())
                        ->helperText('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen'),
                    Select::make("delivery_type")
                        ->label('Verzend type')
                        ->required()
                        ->options(Veloyd::getDeliveryTypes())
                        ->helperText('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen'),
                ];
            })
            ->action(function ($data) {
                $this->validate();

                $veloydOrder = $this->order->veloydOrders()
                    ->where('label_printed', 0)
                    ->where('is_return', false)
                    ->first();

                if (! $veloydOrder) {
                    $veloydOrder = $this->order->veloydOrders()->create([
                        'carrier' => $data['carrier'],
                        'package_type' => $data['package_type'],
                        'delivery_type' => $data['delivery_type'],
                        'is_return' => false,
                    ]);
                } else {
                    $veloydOrder->update([
                        'carrier' => $data['carrier'],
                        'package_type' => $data['package_type'],
                        'delivery_type' => $data['delivery_type'],
                        'is_return' => false,
                    ]);
                }

                try {
                    $result = Veloyd::createConceptAndLabelForOrder($veloydOrder);
                } catch (Throwable $e) {
                    $veloydOrder->error = $e->getMessage();
                    $veloydOrder->save();

                    Notification::make()
                        ->title('Aanmaken van verzendlabel mislukt')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }

                Notification::make()
                    ->title('Verzendlabel aangemaakt')
                    ->body('Het label staat klaar in de lijst hieronder en kan via de download-knop opgehaald worden.')
                    ->success()
                    ->send();

                $this->dispatch('$refresh');

                return null;
            });
    }
}
