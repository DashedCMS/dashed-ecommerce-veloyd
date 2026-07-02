<?php

namespace Dashed\DashedEcommerceVeloyd\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedCore\Traits\HasSettingsPermission;

class VeloydSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Veloyd';

    protected string $view = 'dashed-core::settings.pages.default-settings';
    public array $data = [];
    public array $activatedRegions = [];

    public function mount(): void
    {
        $this->activatedRegions = Countries::getAllSelectedCountries();

        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["veloyd_api_key_{$site['id']}"] = Customsetting::get('veloyd_api_key', $site['id']);
            $formData["veloyd_test_mode_{$site['id']}"] = Customsetting::get('veloyd_test_mode', $site['id'], 0) ? true : false;
            $formData["veloyd_connected_{$site['id']}"] = Customsetting::get('veloyd_connected', $site['id'], 0) ? true : false;
            $formData["veloyd_automatically_push_orders_{$site['id']}"] = Customsetting::get('veloyd_automatically_push_orders', $site['id'], 0) ? true : false;
            $formData["veloyd_auto_handled_after_shipped_days_{$site['id']}"] = (int) Customsetting::get('veloyd_auto_handled_after_shipped_days', $site['id'], 0);
            foreach ($this->activatedRegions as $region) {
                $region = Countries::getCountryIsoCode($region);
                $formData["veloyd_default_package_type_{$region}_{$site['id']}"] = Customsetting::get("veloyd_default_package_type_{$region}", $site['id'], 1);
                $formData["veloyd_default_delivery_type_{$region}_{$site['id']}"] = Customsetting::get("veloyd_default_delivery_type_{$region}", $site['id'], 'Standaard');
                $formData["veloyd_default_carrier_{$region}_{$site['id']}"] = Customsetting::get("veloyd_default_carrier_{$region}", $site['id'], 'PostNL');
                $formData["veloyd_minimum_product_count_{$region}_{$site['id']}"] = Customsetting::get("veloyd_minimum_product_count_{$region}", $site['id'], 2);
                $formData["veloyd_minimum_product_count_package_type_{$region}_{$site['id']}"] = Customsetting::get("veloyd_minimum_product_count_package_type_{$region}", $site['id'], 1);
            }
        }
        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $sites = Sites::getSites();
        $tabGroups = [];

        $tabs = [];
        foreach ($sites as $site) {
            $regionSchemas = [];

            foreach ($this->activatedRegions as $region) {
                $region = Countries::getCountryIsoCode($region);
                $regionSchemas[] = Section::make('Voor bestellingen naar ' . $region)
                    ->schema([
                        Select::make("veloyd_default_carrier_{$region}_{$site['id']}")
                            ->label('Automatische bestelling carrier')
                            ->required(fn (Get $get) => $get("veloyd_automatically_push_orders_{$site['id']}"))
                            ->reactive()
                            ->options(Veloyd::getCarriers()),
                        Select::make("veloyd_default_package_type_{$region}_{$site['id']}")
                            ->label('Automatische bestelling pakket type')
                            ->required(fn (Get $get) => $get("veloyd_automatically_push_orders_{$site['id']}"))
                            ->reactive()
                            ->options(Veloyd::getPackageTypes())
                            ->helperText('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen'),
                        Select::make("veloyd_default_delivery_type_{$region}_{$site['id']}")
                            ->label('Automatisch bestelling verzend type')
                            ->required(fn (Get $get) => $get("veloyd_automatically_push_orders_{$site['id']}"))
                            ->reactive()
                            ->options(Veloyd::getDeliveryTypes())
                            ->helperText('Let op: niet alle opties zijn altijd beschikbaar voor alle adressen'),
                        TextInput::make("veloyd_minimum_product_count_{$region}_{$site['id']}")
                            ->label('Standaard pakket type vanaf een bepaald aantal producten')
                            ->required(fn (Get $get) => $get("veloyd_automatically_push_orders_{$site['id']}"))
                            ->reactive()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000),
                        Select::make("veloyd_minimum_product_count_package_type_{$region}_{$site['id']}")
                            ->label('Standaard pakket type vanaf een bepaald aantal producten')
                            ->required(fn (Get $get) => $get("veloyd_automatically_push_orders_{$site['id']}"))
                            ->reactive()
                            ->options(Veloyd::getPackageTypes()),
                    ])
                    ->columnSpanFull()
                    ->columns(2);
            }

            $newSchema = array_merge([
                TextEntry::make("Veloyd voor {$site['name']}")
                    ->state('Activeer Veloyd.')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextEntry::make("Veloyd is " . (! Customsetting::get('veloyd_connected', $site['id'], 0) ? 'niet' : '') . ' geconnect')
                    ->state(Customsetting::get('veloyd_connection_error', $site['id'], ''))
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("veloyd_api_key_{$site['id']}")
                    ->label('Veloyd API key')
                    ->maxLength(255)
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                Toggle::make("veloyd_test_mode_{$site['id']}")
                    ->label('Test-omgeving gebruiken (test.veloyd.nl)')
                    ->helperText('Zet aan om tegen de Veloyd test-API te werken in plaats van productie.')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                Toggle::make("veloyd_automatically_push_orders_{$site['id']}")
                    ->label('Automatisch bestellingen naar Veloyd pushen')
                    ->reactive()
                    ->helperText('Deze bestellingen komen als concept in Veloyd, pakket type etc kan je nog aanpassen VOORDAT je de label download')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("veloyd_auto_handled_after_shipped_days_{$site['id']}")
                    ->label('Automatisch afhandelen na X dagen verzonden')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Veloyd meldt aflevering niet altijd terug. Staat een order langer dan dit aantal dagen op verzonden, dan wordt die automatisch op afgehandeld gezet (start de opvolg-flow). 0 = uit.')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
            ], $regionSchemas);

            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema($newSchema)
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]);
        }
        $tabGroups[] = Tabs::make('Sites')
            ->tabs($tabs);

        return $schema->schema($tabGroups)
            ->statePath('data');
    }

    public function submit()
    {
        $sites = Sites::getSites();

        foreach ($sites as $site) {
            Customsetting::set('veloyd_api_key', $this->form->getState()["veloyd_api_key_{$site['id']}"], $site['id']);
            Customsetting::set('veloyd_test_mode', $this->form->getState()["veloyd_test_mode_{$site['id']}"], $site['id']);
            Customsetting::set('veloyd_automatically_push_orders', $this->form->getState()["veloyd_automatically_push_orders_{$site['id']}"], $site['id']);
            Customsetting::set('veloyd_auto_handled_after_shipped_days', (int) $this->form->getState()["veloyd_auto_handled_after_shipped_days_{$site['id']}"], $site['id']);
            foreach ($this->activatedRegions as $region) {
                $region = Countries::getCountryIsoCode($region);
                Customsetting::set("veloyd_default_package_type_{$region}", $this->form->getState()["veloyd_default_package_type_{$region}_{$site['id']}"], $site['id']);
                Customsetting::set("veloyd_default_delivery_type_{$region}", $this->form->getState()["veloyd_default_delivery_type_{$region}_{$site['id']}"], $site['id']);
                Customsetting::set("veloyd_default_carrier_{$region}", $this->form->getState()["veloyd_default_carrier_{$region}_{$site['id']}"], $site['id']);
                Customsetting::set("veloyd_minimum_product_count_{$region}", $this->form->getState()["veloyd_minimum_product_count_{$region}_{$site['id']}"], $site['id']);
                Customsetting::set("veloyd_minimum_product_count_package_type_{$region}", $this->form->getState()["veloyd_minimum_product_count_package_type_{$region}_{$site['id']}"], $site['id']);
            }
            Customsetting::set('veloyd_connected', Veloyd::isConnected($site['id']), $site['id']);
        }

        Notification::make()
            ->title('De Veloyd instellingen zijn opgeslagen')
            ->success()
            ->send();

        return redirect(VeloydSettingsPage::getUrl());
    }
}
