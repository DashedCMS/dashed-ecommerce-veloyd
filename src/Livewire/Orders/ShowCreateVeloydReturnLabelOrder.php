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
use Symfony\Component\HttpFoundation\Response;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;
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

    public function existingReturnLabel(): ?VeloydOrder
    {
        return $this->order->veloydOrders()
            ->where('is_return', true)
            ->whereNotNull('label_pdf_path')
            ->latest()
            ->first();
    }

    public function downloadReturnLabel(): ?Response
    {
        $veloydOrder = $this->existingReturnLabel();

        if (! $veloydOrder) {
            Notification::make()
                ->title(__('Geen retourlabel gevonden'))
                ->body(__('Maak eerst een retourlabel aan.'))
                ->danger()
                ->send();

            return null;
        }

        if (! Storage::disk('public')->exists($veloydOrder->label_pdf_path)) {
            Notification::make()
                ->title(__('Label-bestand ontbreekt'))
                ->body(__('Het PDF-bestand is niet meer aanwezig op de server. Maak het retourlabel opnieuw aan.'))
                ->danger()
                ->send();

            return null;
        }

        if (! $veloydOrder->label_printed) {
            $veloydOrder->label_printed = true;
            $veloydOrder->save();
        }

        $filename = 'retour-label-' . ($veloydOrder->order->invoice_id ?? $veloydOrder->id) . '.pdf';

        return Storage::disk('public')->download($veloydOrder->label_pdf_path, $filename);
    }

    public function render()
    {
        return view('dashed-ecommerce-veloyd::orders.components.create-return-label');
    }

    public function action(): Action
    {
        return Action::make('action')
            ->label(__('Retourlabel aanmaken'))
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
                    ->label(__('Vervoerder'))
                    ->required()
                    ->options(Veloyd::getCarriers()),
                Select::make('package_type')
                    ->label(__('Pakket type'))
                    ->required()
                    ->options(Veloyd::getPackageTypes())
                    ->helperText(__('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen.')),
                Select::make('delivery_type')
                    ->label(__('Verzend type'))
                    ->required()
                    ->options(Veloyd::getDeliveryTypes())
                    ->helperText(__('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen.')),
                Toggle::make('send_email_to_customer')
                    ->label(__('Mail klant met label als bijlage'))
                    ->default(true),
                Textarea::make('personal_note')
                    ->label(__('Persoonlijke notitie aan klant'))
                    ->rows(4)
                    ->nullable()
                    ->helperText(__('Optioneel. Wordt onder de standaardtekst toegevoegd in de mail.')),
            ])
            ->modalSubmitActionLabel(__('Retourlabel aanmaken'))
            ->modalHeading(__('Retourlabel aanmaken'))
            ->modalDescription(__('Maak een retourlabel aan voor deze bestelling. Het label wordt na bevestigen gedownload, en eventueel direct naar de klant gemaild.'))
            ->action(function (array $data) {
                if (! Veloyd::isConnected($this->order->site_id)) {
                    Notification::make()
                        ->title(__('Veloyd niet geconnect'))
                        ->body(__('Controleer de API sleutel in de Veloyd instellingen.'))
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
                        ->title(__('Aanmaken van retourlabel mislukt'))
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }

                $labelUrl = Storage::disk('public')->url($result['filePath']);

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
                            ->title(__('Retourlabel verstuurd naar klant'))
                            ->body(__('De mail is verzonden naar :email.', ['email' => $this->order->email]))
                            ->success()
                            ->send();

                        return null;
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(__('Mail naar klant mislukt'))
                            ->body(__('Het label is wel aangemaakt, maar de mail kon niet verstuurd worden: :fout', ['fout' => $e->getMessage()]))
                            ->warning()
                            ->send();
                    }
                }

                $this->js('window.open(' . json_encode($labelUrl) . ", '_blank');");

                Notification::make()
                    ->title(__('Retourlabel aangemaakt'))
                    ->body(__('Het label is geopend in een nieuw tabblad.'))
                    ->success()
                    ->send();

                return null;
            });
    }
}
