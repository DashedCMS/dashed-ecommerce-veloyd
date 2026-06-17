<?php

namespace Dashed\DashedEcommerceVeloyd;

use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedEcommerceVeloyd\Commands\CheckVeloydOrders;
use Dashed\DashedEcommerceVeloyd\Livewire\Orders\ShowVeloydOrders;
use Dashed\DashedEcommerceVeloyd\Commands\BackfillVeloydTrackTraces;
use Dashed\DashedEcommerceVeloyd\Commands\CreateVeloydConceptOrders;
use Dashed\DashedEcommerceVeloyd\Livewire\Orders\ShowPushToVeloydOrder;
use Dashed\DashedEcommerceVeloyd\Filament\Pages\Settings\VeloydSettingsPage;
use Dashed\DashedEcommerceVeloyd\Livewire\Orders\ShowCreateVeloydReturnLabelOrder;

class DashedEcommerceVeloydServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-ecommerce-veloyd';

    public function bootingPackage()
    {
        Livewire::component('show-push-to-veloyd-order', ShowPushToVeloydOrder::class);
        Livewire::component('show-create-veloyd-return-label-order', ShowCreateVeloydReturnLabelOrder::class);
        Livewire::component('show-veloyd-orders', ShowVeloydOrders::class);

        Order::addDynamicRelation('veloydOrders', function (Order $model) {
            return $model->hasMany(VeloydOrder::class);
        });

        \Illuminate\Support\Facades\Event::listen(
            \Dashed\DashedEcommerceCore\Events\Orders\OrderReturnApprovedEvent::class,
            \Dashed\DashedEcommerceVeloyd\Listeners\CreateVeloydReturnLabelListener::class
        );

        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command(CreateVeloydConceptOrders::class)->everyMinute()->withoutOverlapping();
            $schedule->command(CheckVeloydOrders::class)->everyFifteenMinutes()->withoutOverlapping();
            $schedule->command(\Dashed\DashedEcommerceVeloyd\Commands\SyncVeloydStatuses::class)->hourly()->withoutOverlapping();
            // Vangt zendingen op waarvan de T&T pas later (asynchroon) door de vervoerder is
            // toegekend, inclusief al afgehandelde zendingen die de status-sync overslaat.
            $schedule->command(BackfillVeloydTrackTraces::class)->daily()->withoutOverlapping();
        });

        cms()->registerSettingsDocs(
            page: \Dashed\DashedEcommerceVeloyd\Filament\Pages\Settings\VeloydSettingsPage::class,
            title: 'Veloyd instellingen',
            intro: 'Koppel de webshop met Veloyd zodat verzendlabels automatisch aangemaakt kunnen worden. Per site leg je de API key vast en bepaal je per regio welke vervoerder en welk pakkettype standaard gebruikt worden. Heb je meerdere sites? Dan stel je dit per site apart in.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => <<<MARKDOWN
Op deze pagina regel je drie dingen:

1. De koppeling met je Veloyd account via een API key.
2. Of nieuwe bestellingen automatisch naar Veloyd worden doorgezet.
3. Per regio (land of regio waar je naar verstuurt) welke vervoerder, welk pakkettype en welk verzendtype standaard gekozen worden.

De regio velden zie je terug per land waar je naartoe verzendt. De keuzes die je daar maakt worden voorgevuld zodra je een label aanmaakt, je kunt ze per bestelling nog aanpassen.
MARKDOWN,
                ],
                [
                    'heading' => 'Hoe zet je dit op?',
                    'body' => <<<MARKDOWN
1. Log in op je Veloyd account (https://app.veloyd.nl).
2. Ga naar je accountinstellingen en open het onderdeel voor de API.
3. Kopieer de API key en plak deze in het veld hieronder.
4. Bepaal of je bestellingen automatisch wilt doorzetten.
5. Loop de regio\'s langs en kies per regio de standaard vervoerder, het pakkettype en het verzendtype.
6. Sla de instellingen op.
MARKDOWN,
                ],
            ],
            fields: [
                'API sleutel' => 'De API key uit je Veloyd account. Zonder deze sleutel kan de webshop geen labels aanmaken of bestellingen doorzetten.',
                'Test-omgeving' => 'Schakel dit aan om tegen test.veloyd.nl te werken in plaats van productie.',
                'Automatisch bestellingen pushen' => 'Zet je dit aan, dan worden nieuwe bestellingen direct in Veloyd klaargezet zodra ze betaald zijn. Staat dit uit, dan stuur je bestellingen handmatig door vanuit het bestellingenoverzicht.',
                'Standaard vervoerder (per regio)' => 'De vervoerder die standaard wordt voorgesteld voor verzendingen naar deze regio, bijvoorbeeld PostNL of DHL. Dit veld stel je per regio apart in.',
                'Standaard pakkettype (per regio)' => 'Het type pakket dat standaard wordt gekozen, bijvoorbeeld een gewoon pakket of een brievenbuspakje. Per regio in te stellen.',
                'Standaard verzendtype (per regio)' => 'Hoe het pakket bij de klant moet komen: standaard bezorgen, spoed of via een afhaalpunt. Per regio in te stellen.',
                'Drempel aantal producten' => 'Vanaf dit aantal producten in een bestelling wordt automatisch een ander pakkettype gekozen. Handig als kleine bestellingen in een brievenbuspakje passen, maar grotere bestellingen niet.',
                'Pakkettype boven drempel' => 'Het pakkettype dat gebruikt wordt zodra de drempel bij het aantal producten is bereikt. Per regio in te stellen.',
            ],
            tips: [
                'Test de koppeling eerst met een proefbestelling. Zo zie je meteen of de API key werkt en of de standaardkeuzes per regio kloppen.',
                'Heb je meerdere landen waar je naartoe verzendt? Loop ze allemaal even langs, anders pakt de webshop voor die regio geen voorkeur en moet je het per bestelling met de hand kiezen.',
                'Zet automatisch doorzetten pas aan als je zeker weet dat de standaardinstellingen per regio kloppen, anders kunnen er labels worden aangemaakt met het verkeerde pakkettype.',
            ],
        );
    }

    public function configurePackage(Package $package): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $package
            ->name('dashed-ecommerce-veloyd')
            ->hasRoutes([
                'VeloydRoutes',
            ])
            ->hasCommands([
                CheckVeloydOrders::class,
                CreateVeloydConceptOrders::class,
                \Dashed\DashedEcommerceVeloyd\Commands\SyncVeloydStatuses::class,
                BackfillVeloydTrackTraces::class,
            ])
            ->hasViews();

        cms()->registerSettingsPage(VeloydSettingsPage::class, 'Veloyd', 'archive-box', 'Koppel Veloyd');

        if (method_exists(cms(), 'registerIntegration')) {
            cms()->registerIntegration([
                'slug' => 'veloyd',
                'label' => 'Veloyd',
                'icon' => 'heroicon-o-truck',
                'category' => 'shipping',
                'settings_page' => VeloydSettingsPage::class,
                'health_check' => fn (?string $siteId = null) => \Dashed\DashedCore\Integrations\IntegrationHealth::fromSettings(['veloyd_api_key'], $siteId, 'API key ontbreekt'),
                'package' => 'dashed-ecommerce-veloyd',
            ]);
        }

        ecommerce()->widgets(
            'orders',
            array_merge(ecommerce()->widgets('orders'), [
                'show-push-to-veloyd-order' => [
                    'name' => 'show-push-to-veloyd-order',
                    'width' => 'sidebar',
                ],
                'show-create-veloyd-return-label-order' => [
                    'name' => 'show-create-veloyd-return-label-order',
                    'width' => 'sidebar',
                ],
                'show-veloyd-orders' => [
                    'name' => 'show-veloyd-orders',
                    'width' => 'sidebar',
                ],
            ])
        );

        cms()->builder('plugins', [
            new DashedEcommerceVeloydPlugin(),
        ]);

        cms()->builder('summaryContributors', [\Dashed\DashedEcommerceVeloyd\Services\Summary\VeloydSummaryContributor::class]);
    }
}
