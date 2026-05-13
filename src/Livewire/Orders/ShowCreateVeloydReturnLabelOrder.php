<?php

namespace Dashed\DashedEcommerceVeloyd\Livewire\Orders;

use Throwable;
use Livewire\Component;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Dashed\DashedEcommerceVeloyd\Mail\ReturnLabelMail;

/**
 * Sidebar-action op de ViewOrder pagina voor het aanmaken van een retourlabel.
 */
class ShowCreateVeloydReturnLabelOrder extends Component implements HasSchemas, HasActions
{
    use InteractsWithSchemas;
    use InteractsWithActions;

    public Order $order;

    public function mount(Order $order): void
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
            ->label('Retourlabel aanmaken')
            ->color('warning')
            ->icon('heroicon-o-arrow-uturn-left')
            ->fillForm(fn () => [
                'carrier' => Customsetting::get('veloyd_default_carrier_' . $this->order->countryIsoCode, $this->order->site_id, 'PostNL'),
                'package_type' => Customsetting::get('veloyd_default_package_type_' . $this->order->countryIsoCode, $this->order->site_id, 1),
                'delivery_type' => Customsetting::get('veloyd_default_delivery_type_' . $this->order->countryIsoCode, $this->order->site_id, 'Standaard'),
                'send_email_to_customer' => true,
                'personal_note' => null,
            ])
            ->schema([
                Select::make('carrier')
                    ->label('Vervoerder')
                    ->required()
                    ->options(Veloyd::getCarriers()),
                Select::make('package_type')
                    ->label('Pakket type')
                    ->required()
                    ->options(Veloyd::getPackageTypes())
                    ->helperText('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen.'),
                Select::make('delivery_type')
                    ->label('Verzend type')
                    ->required()
                    ->options(Veloyd::getDeliveryTypes())
                    ->helperText('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen.'),
                Toggle::make('send_email_to_customer')
                    ->label('Mail klant met label als bijlage')
                    ->default(true),
                Textarea::make('personal_note')
                    ->label('Persoonlijke notitie aan klant')
                    ->rows(4)
                    ->nullable()
                    ->helperText('Optioneel. Wordt onder de standaardtekst toegevoegd in de mail.'),
            ])
            ->modalSubmitActionLabel('Retourlabel aanmaken')
            ->modalHeading('Retourlabel aanmaken')
            ->modalDescription('Maak een retourlabel aan voor deze bestelling. Het label wordt na bevestigen gedownload, en eventueel direct naar de klant gemaild.')
            ->action(function (array $data) {
                if (! Veloyd::isConnected($this->order->site_id)) {
                    Notification::make()
                        ->title('Veloyd niet geconnect')
                        ->body('Controleer de API sleutel in de Veloyd instellingen.')
                        ->danger()
                        ->send();

                    return null;
                }

                $personalNote = ! empty($data['personal_note']) ? trim((string) $data['personal_note']) : null;

                $veloydOrder = $this->order->veloydOrders()->create([
                    'carrier' => $data['carrier'],
                    'package_type' => $data['package_type'],
                    'delivery_type' => $data['delivery_type'],
                    'is_return' => true,
                    'personal_note' => $personalNote,
                ]);

                try {
                    $result = Veloyd::createReturnLabelForOrder($veloydOrder);
                } catch (Throwable $e) {
                    $veloydOrder->error = $e->getMessage();
                    $veloydOrder->save();

                    Notification::make()
                        ->title('Aanmaken van retourlabel mislukt')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }

                if (! empty($data['send_email_to_customer']) && $this->order->email) {
                    try {
                        Mail::to($this->order->email)->send(new ReturnLabelMail(
                            $this->order,
                            $result['filePath'],
                            $personalNote
                        ));

                        $veloydOrder->is_label_email_sent = true;
                        $veloydOrder->save();

                        Notification::make()
                            ->title('Retourlabel verstuurd naar klant')
                            ->body('De mail is verzonden naar ' . $this->order->email . '.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Mail naar klant mislukt')
                            ->body('Het label is wel aangemaakt, maar de mail kon niet verstuurd worden: ' . $e->getMessage())
                            ->warning()
                            ->send();
                    }
                } else {
                    Notification::make()
                        ->title('Retourlabel aangemaakt')
                        ->body('Het label staat klaar om te downloaden.')
                        ->success()
                        ->send();
                }

                return redirect()->away(Storage::disk('public')->url($result['filePath']));
            });
    }
}
