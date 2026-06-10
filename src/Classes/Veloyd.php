<?php

namespace Dashed\DashedEcommerceVeloyd\Classes;

use Exception;
use Throwable;
use Illuminate\Support\Facades\Log;
use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceVeloyd\Models\VeloydOrder;

/**
 * HTTP-based client voor de Veloyd API (https://app.veloyd.nl/apidoc/).
 * Veloyd heeft geen officiele PHP SDK, daarom praten we direct via de Http
 * facade. Auth: `Authorization: Apikey <key>` uit Customsetting. Statuscodes:
 *   1=registered (concept), 2=confirmed (label gedrukt), 3=collected,
 *   4=in transit, 5=manco, 6=available at pickup point, 7=delivered,
 *   8=returned to sender, 9=cancelled, 10=see t&t page.
 */
class Veloyd
{
    public const LABEL_BATCH_SIZE = 20;

    public static function getUserAgent(): string
    {
        return 'DashedCMS/2.0 PHP/8.2';
    }

    public static function apiKey(?string $siteId = null): string
    {
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        return (string) Customsetting::get('veloyd_api_key', $siteId, disableCache: true);
    }

    public static function baseUrl(?string $siteId = null): string
    {
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        if (Customsetting::get('veloyd_test_mode', $siteId, 0)) {
            return 'https://test.veloyd.nl/api';
        }

        return 'https://app.veloyd.nl/api';
    }

    protected static function client(?string $siteId = null)
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => self::getUserAgent(),
            'Authorization' => 'Apikey ' . self::apiKey($siteId),
        ]);
    }

    public static function connectOrderWithCarrier(Order $order)
    {
        if (self::isConnected($order->site_id) && ! $order->veloydOrders()->count()) {
            $packageTypeIds = [];

            foreach ($order->orderProducts as $orderProduct) {
                if ($orderProduct->product) {
                    $packageTypeIds[] = $orderProduct->product->productGroup->contentBlocks['veloyd-package-type']
                        ?? Customsetting::get('veloyd_default_package_type_' . $order->countryIsoCode, $order->site_id);
                }
            }

            $order->veloydOrders()->create([
                'carrier' => Customsetting::get('veloyd_default_carrier_' . $order->countryIsoCode, $order->site_id, 'PostNL'),
                'package_type' => self::getBiggestPackageNeededByIds($order->countryIsoCode, $packageTypeIds, $order->site_id),
                'delivery_type' => Customsetting::get('veloyd_default_delivery_type_' . $order->countryIsoCode, $order->site_id, 'Standaard'),
            ]);

            $orderLog = new OrderLog();
            $orderLog->order_id = $order->id;
            $orderLog->user_id = null;
            $orderLog->tag = 'system.note.created';
            $orderLog->note = 'Bestelling klaargezet voor Veloyd';
            $orderLog->save();
        } elseif (! self::isConnected($order->site_id)) {
            $orderLog = new OrderLog();
            $orderLog->order_id = $order->id;
            $orderLog->user_id = null;
            $orderLog->tag = 'system.note.created';
            $orderLog->note = 'Veloyd niet geconnect, bestelling niet klaargezet voor Veloyd';
            $orderLog->save();
        }
    }

    public static function isConnected($siteId = null): bool
    {
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        $apiKey = self::apiKey($siteId);
        if (! $apiKey) {
            Customsetting::set('veloyd_connection_error', 'API sleutel ontbreekt', $siteId);

            return false;
        }

        try {
            // Veloyd kent geen dedicated ping. We doen een options-aanvraag met
            // een dummy NL-adres; bij ongeldige API key komt 401/403 terug,
            // bij een geldige key krijgen we altijd opties (mogelijk leeg).
            $response = self::client($siteId)
                ->post(self::baseUrl($siteId) . '/parcel/options', [
                    'parcel' => [
                        'address' => [
                            'name' => 'Test',
                            'street' => 'Teststraat',
                            'nr' => '1',
                            'postalCode' => '1011AB',
                            'city' => 'Amsterdam',
                            'country' => 'NL',
                        ],
                        'weight' => 1000,
                    ],
                ]);

            if ($response->status() === 401 || $response->status() === 403) {
                Customsetting::set('veloyd_connection_error', 'API sleutel ongeldig (' . $response->status() . ')', $siteId);

                return false;
            }

            if ($response->failed() && $response->status() !== 422) {
                Customsetting::set('veloyd_connection_error', 'Kan niet verbinden (status ' . $response->status() . ')', $siteId);

                return false;
            }

            Customsetting::set('veloyd_connection_error', null, $siteId);

            return true;
        } catch (Throwable $e) {
            Customsetting::set('veloyd_connection_error', $e->getMessage(), $siteId);

            return false;
        }
    }

    /**
     * Bouwt de Veloyd /parcel/create payload op vanuit een VeloydOrder.
     * Used by zowel createConcepts() (bulk) als createConceptAndLabelForOrder()
     * (per-order) zodat we maar één plek hebben waar adres/options gemapped
     * worden.
     */
    protected static function buildParcelPayload(VeloydOrder $veloydOrder, bool $isReturn = false): array
    {
        $order = $veloydOrder->order;

        $options = [];
        if ($isReturn) {
            // Volgens Velocity (leverancier Veloyd): een retourpakket is
            // een standaard zending met de extra verzendoptie "Retour".
            // Veloyd swapt zelf de rollen (Ontvanger -> Afzender), dus we
            // sturen de klantgegevens nog steeds in het address-veld.
            $options[] = 'Retour';
        }
        if ($veloydOrder->delivery_type && $veloydOrder->delivery_type !== 'Standaard') {
            $options[] = $veloydOrder->delivery_type;
        }

        $packageDefaults = self::getPackageTypeDefaults((int) $veloydOrder->package_type);

        $payload = [
            'parcel' => [
                'address' => [
                    'name' => trim($order->name ?: ($order->first_name . ' ' . $order->last_name)),
                    'street' => (string) $order->street,
                    'nr' => (string) $order->house_nr,
                    'postalCode' => trim((string) $order->zip_code),
                    'city' => (string) $order->city,
                    'country' => (string) $order->countryIsoCode,
                    'email' => (string) $order->email,
                    'phone' => (string) $order->phone_number,
                ],
                'carrier' => (string) $veloydOrder->carrier,
                'weight' => $packageDefaults['weight'],
                'length' => $packageDefaults['length'],
                'width' => $packageDefaults['width'],
                'height' => $packageDefaults['height'],
                'reference' => 'Bestelling ' . ($isReturn ? 'retour ' : '') . $order->invoice_id,
            ],
        ];

        if (! empty($options)) {
            $payload['parcel']['options'] = $options;
        }

        return $payload;
    }

    public static function createConcepts(): array
    {
        $siteId = Sites::getActive();

        // Sla orders met een al-gezette error over: dezelfde input geeft
        // dezelfde API-fout, dus elke minuut opnieuw proberen levert geen
        // nieuwe info en zorgt voor mail-spam naar de admins. De "Opnieuw
        // in wachtrij zetten"-actie op de Filament-page zet error op null,
        // waarna deze cron-run 'm weer oppakt.
        $veloydOrders = VeloydOrder::where('label_printed', 0)
            ->whereNull('shipment_id')
            ->whereNull('error')
            ->get();

        $orders = [];
        $failures = [];

        foreach ($veloydOrders as $veloydOrder) {
            if ($veloydOrder->order->site_id !== $siteId) {
                continue;
            }

            if (! $veloydOrder->carrier) {
                $countryIsoCode = $veloydOrder->order->countryIsoCode;
                // Gelijk aan de manual-flow fallback in ShowPushToVeloydOrder:
                // PostNL als default zodat een lege customsetting niet leidt
                // tot een halfaangemaakte VeloydOrder zonder carrier.
                $defaultCarrier = Customsetting::get('veloyd_default_carrier_' . $countryIsoCode, $veloydOrder->order->site_id, 'PostNL');

                if ($defaultCarrier) {
                    $order = $veloydOrder->order;
                    $veloydOrder->delete();
                    self::connectOrderWithCarrier($order);
                    $veloydOrder = $order->veloydOrders()->first();
                }
            }

            if (! $veloydOrder || ! $veloydOrder->carrier) {
                if ($veloydOrder) {
                    $veloydOrder->error = 'Geen standaard Veloyd-carrier ingesteld voor land ' . ($veloydOrder->order->countryIsoCode ?? '?') . '. Configureer de carrier en gebruik "Opnieuw in wachtrij zetten".';
                    $veloydOrder->save();

                    $failures[] = [
                        'invoice_id' => $veloydOrder->order->invoice_id ?? $veloydOrder->order_id,
                        'order_id' => $veloydOrder->order_id,
                        'message' => $veloydOrder->error,
                    ];
                }

                continue;
            }

            try {
                $response = self::client($veloydOrder->order->site_id)
                    ->post(self::baseUrl($veloydOrder->order->site_id) . '/parcel/create', self::buildParcelPayload($veloydOrder));

                if ($response->failed()) {
                    throw new Exception('Veloyd /parcel/create faalde (' . $response->status() . '): ' . $response->body());
                }

                $shipmentId = $response->json('id');
                if (! $shipmentId) {
                    throw new Exception('Veloyd gaf geen shipment id terug.');
                }

                $veloydOrder->shipment_id = $shipmentId;
                $veloydOrder->error = null;
                $veloydOrder->save();

                $orders[] = $veloydOrder->order;
            } catch (Throwable $e) {
                $veloydOrder->error = $e->getMessage();
                $veloydOrder->save();

                $failures[] = [
                    'invoice_id' => $veloydOrder->order->invoice_id ?? $veloydOrder->order_id,
                    'order_id' => $veloydOrder->order_id,
                    'message' => $e->getMessage(),
                ];

                Log::warning('Veloyd concept creation failed', [
                    'veloyd_order_id' => $veloydOrder->id,
                    'order_id' => $veloydOrder->order_id,
                    'invoice_id' => $veloydOrder->order->invoice_id ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (! empty($failures)) {
            $lines = array_map(function ($failure) {
                $line = "Bestelling {$failure['invoice_id']}: {$failure['message']}";

                $url = $failure['order_id']
                    ? rescue(fn () => route('filament.dashed.resources.orders.view', ['record' => $failure['order_id']]), null, false)
                    : null;

                if ($url) {
                    $line .= '<br><a href="' . $url . '" style="display: inline-block; margin-top: 8px; padding: 8px 16px; background-color: #000000; color: #ffffff; border-radius: 6px; text-decoration: none; font-weight: bold;">Bekijk bestelling</a>';
                }

                return $line;
            }, $failures);

            Mails::sendNotificationToAdmins(
                'Veloyd kon de volgende bestellingen niet als concept aanmaken. Corrigeer de gegevens en probeer opnieuw:<br><br>' . implode('<br><br>', $lines),
                count($failures) === 1
                    ? "Veloyd sync mislukt voor bestelling {$failures[0]['invoice_id']}"
                    : 'Veloyd sync mislukt voor ' . count($failures) . ' bestellingen'
            );
        }

        return [
            'orders' => $orders,
            'failures' => $failures,
        ];
    }

    public static function unprintedOrdersForBatch(string $siteId, int $limit = self::LABEL_BATCH_SIZE)
    {
        return VeloydOrder::where('label_printed', 0)
            ->where('is_return', false)
            ->whereNotNull('shipment_id')
            ->whereHas('order', fn ($q) => $q->where('site_id', $siteId))
            ->limit($limit)
            ->get();
    }

    public static function unprintedCount(string $siteId): int
    {
        return VeloydOrder::where('label_printed', 0)
            ->where('is_return', false)
            ->whereNotNull('shipment_id')
            ->whereHas('order', fn ($q) => $q->where('site_id', $siteId))
            ->count();
    }

    /**
     * Bulk-label download. Per shipment_id wordt de /parcel/label endpoint
     * aangeroepen, de base64 PDF gedecodeerd en samengevoegd tot één bestand.
     * Veloyd levert per parcel een eigen PDF; we mergen ze met een simpele
     * concat-aanpak (FPDI is hier overkill, dus we slaan ze los op en gebruiken
     * ghostscript indien beschikbaar, anders schrijven we een ZIP).
     */
    public static function createShipments(): array
    {
        $siteId = Sites::getActive();

        $veloydOrders = self::unprintedOrdersForBatch($siteId);

        $orders = [];
        $pdfPaths = [];

        foreach ($veloydOrders as $veloydOrder) {
            if ($veloydOrder->order->site_id !== $siteId) {
                continue;
            }

            try {
                $labelData = self::fetchLabel($veloydOrder);

                $pdf = base64_decode($labelData['label'] ?? '');
                if (! $pdf) {
                    throw new Exception('Lege PDF terug van Veloyd voor shipment ' . $veloydOrder->shipment_id);
                }

                $filePath = 'dashed/orders/veloyd/label-' . $veloydOrder->order->invoice_id . '-' . $veloydOrder->id . '.pdf';
                Storage::disk('public')->put($filePath, $pdf);
                $pdfPaths[] = $filePath;

                $trackTrace = $labelData['trackTrace'] ?? null;
                $trackTraceLink = $labelData['trackTraceLink'] ?? null;

                if ($trackTrace) {
                    $veloydOrder->track_and_trace = [[
                        $trackTrace => $trackTraceLink ?: '',
                    ]];

                    $veloydOrder->order->addTrackAndTrace(
                        'veloyd',
                        $veloydOrder->carrier ?: 'Veloyd',
                        $trackTrace,
                        $trackTraceLink ?: ''
                    );
                }

                $veloydOrder->label_pdf_path = $filePath;
                $veloydOrder->label_printed = 1;
                $veloydOrder->save();

                $orders[] = $veloydOrder->order;
            } catch (Throwable $e) {
                $veloydOrder->error = $e->getMessage();
                $veloydOrder->save();

                Log::warning('Veloyd label fetch failed', [
                    'veloyd_order_id' => $veloydOrder->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Combineer alle losse PDFs tot één download. We schrijven elk PDF
        // afzonderlijk in een verzamelmap en proberen via gs (ghostscript)
        // te mergen — bij afwezigheid valt het terug op het eerste PDF zodat
        // de admin altijd iets bruikbaars krijgt.
        $combinedPath = self::combinePdfs($pdfPaths);

        return [
            'filePath' => $combinedPath,
            'orders' => $orders,
            'processed' => count($orders),
            'hasMore' => self::unprintedCount($siteId) > 0,
        ];
    }

    /**
     * Maakt voor één VeloydOrder een concept aan bij Veloyd en haalt direct
     * het label PDF op. PDF wordt opgeslagen in de public disk en het pad
     * teruggegeven samen met track-en-trace data.
     */
    public static function createConceptAndLabelForOrder(VeloydOrder $veloydOrder): array
    {
        if (! $veloydOrder->carrier) {
            throw new Exception('Geen vervoerder ingesteld op deze Veloyd order.');
        }

        // Stap 1: concept aanmaken (status 1 in Veloyd)
        if (! $veloydOrder->shipment_id) {
            $response = self::client($veloydOrder->order->site_id)
                ->post(self::baseUrl($veloydOrder->order->site_id) . '/parcel/create', self::buildParcelPayload($veloydOrder));

            if ($response->failed()) {
                throw new Exception('Veloyd /parcel/create faalde (' . $response->status() . '): ' . $response->body());
            }

            $shipmentId = $response->json('id');
            if (! $shipmentId) {
                throw new Exception('Veloyd gaf geen shipment id terug.');
            }

            $veloydOrder->shipment_id = $shipmentId;
            $veloydOrder->error = null;
            $veloydOrder->save();
        }

        // Stap 2: label PDF ophalen (zet status door naar 2 = confirmed)
        $labelData = self::fetchLabel($veloydOrder);

        $pdf = base64_decode($labelData['label'] ?? '');
        if (! $pdf) {
            throw new Exception('Lege PDF terug van Veloyd voor shipment ' . $veloydOrder->shipment_id);
        }

        $trackTrace = $labelData['trackTrace'] ?? null;
        $trackTraceLink = $labelData['trackTraceLink'] ?? null;

        if ($trackTrace) {
            $veloydOrder->track_and_trace = [[
                $trackTrace => $trackTraceLink ?: '',
            ]];

            $veloydOrder->order->addTrackAndTrace(
                'veloyd',
                $veloydOrder->carrier ?: 'Veloyd',
                $trackTrace,
                $trackTraceLink ?: ''
            );
        }

        $filePath = 'dashed/orders/veloyd/label-' . $veloydOrder->order->invoice_id . '-' . time() . '.pdf';
        Storage::disk('public')->put($filePath, $pdf);

        // label_printed bewust 0 laten: pas wanneer de admin op de
        // download-knop klikt zetten we 'm op 1. Dit voorkomt dat de "Label
        // gedownload"-badge verschijnt voordat het PDF echt is opgehaald.
        $veloydOrder->label_pdf_path = $filePath;
        $veloydOrder->save();

        return [
            'filePath' => $filePath,
            'pdf' => $pdf,
            'veloydOrder' => $veloydOrder,
        ];
    }

    /**
     * Maakt een retourzending aan bij Veloyd voor één order. Veloyd kent
     * geen dedicated /parcel/return endpoint zoals MyParcel; we gebruiken
     * /parcel/create met de "Retour" verzendoptie. Klantgegevens blijven
     * in het address-veld; Veloyd swapt zelf Ontvanger -> Afzender op
     * basis van de Retour-optie. Het label wordt direct opgehaald,
     * opgeslagen en teruggegeven.
     */
    public static function createReturnLabelForOrder(VeloydOrder $veloydOrder): array
    {
        if (! $veloydOrder->carrier) {
            throw new Exception('Geen vervoerder ingesteld op deze Veloyd order.');
        }

        $response = self::client($veloydOrder->order->site_id)
            ->post(self::baseUrl($veloydOrder->order->site_id) . '/parcel/create', self::buildParcelPayload($veloydOrder, isReturn: true));

        if ($response->failed()) {
            throw new Exception('Veloyd /parcel/create (retour) faalde (' . $response->status() . '): ' . $response->body());
        }

        $shipmentId = $response->json('id');
        if (! $shipmentId) {
            throw new Exception('Veloyd gaf geen shipment id terug voor het retourlabel.');
        }

        $veloydOrder->shipment_id = $shipmentId;
        $veloydOrder->is_return = true;
        $veloydOrder->error = null;
        $veloydOrder->save();

        $labelData = self::fetchLabel($veloydOrder);
        $pdf = base64_decode($labelData['label'] ?? '');
        if (! $pdf) {
            throw new Exception('Lege retour-PDF terug van Veloyd voor shipment ' . $shipmentId);
        }

        $trackTrace = $labelData['trackTrace'] ?? null;
        $trackTraceLink = $labelData['trackTraceLink'] ?? null;

        if ($trackTrace) {
            $veloydOrder->track_and_trace = [[
                $trackTrace => $trackTraceLink ?: '',
            ]];
        }

        $filePath = 'dashed/orders/veloyd/return-label-' . $veloydOrder->order->invoice_id . '-' . time() . '.pdf';
        Storage::disk('public')->put($filePath, $pdf);

        $veloydOrder->label_pdf_path = $filePath;
        $veloydOrder->save();

        return [
            'filePath' => $filePath,
            'pdf' => $pdf,
            'veloydOrder' => $veloydOrder,
        ];
    }

    public static function fetchLabel(VeloydOrder $veloydOrder): array
    {
        $response = self::client($veloydOrder->order->site_id)
            ->get(self::baseUrl($veloydOrder->order->site_id) . '/parcel/label/' . $veloydOrder->shipment_id);

        if ($response->failed()) {
            throw new Exception('Veloyd /parcel/label faalde (' . $response->status() . '): ' . $response->body());
        }

        return (array) $response->json();
    }

    /**
     * Haalt het label-PDF voor één Veloyd-order on-demand op (o.b.v. shipment_id)
     * en slaat het op de public disk op + zet label_pdf_path. Idempotent. Geeft
     * het pad terug, of null als er geen shipment is.
     */
    public static function downloadLabelForOrder(VeloydOrder $veloydOrder): ?string
    {
        if ($veloydOrder->label_pdf_path && Storage::disk('public')->exists($veloydOrder->label_pdf_path)) {
            return $veloydOrder->label_pdf_path;
        }

        if (! $veloydOrder->shipment_id) {
            return null;
        }

        $labelData = self::fetchLabel($veloydOrder);
        $pdf = base64_decode($labelData['label'] ?? '');
        if (! $pdf) {
            throw new Exception('Leeg label-PDF terug van Veloyd voor shipment ' . $veloydOrder->shipment_id);
        }

        $filePath = 'dashed/orders/veloyd/label-'
            . ($veloydOrder->order->invoice_id ?: $veloydOrder->order_id)
            . '-' . $veloydOrder->id . '.pdf';
        Storage::disk('public')->put($filePath, $pdf);

        $veloydOrder->label_pdf_path = $filePath;
        $veloydOrder->save();

        return $filePath;
    }

    public static function getShipment(int|string $shipmentId, string $siteId): array
    {
        $response = self::client($siteId)
            ->get(self::baseUrl($siteId) . '/parcel/get/' . $shipmentId);

        return (array) $response->json();
    }

    public static function getLabelsFromShipments(array $shipmentIds = [], ?string $siteId = null): array
    {
        if (! $siteId) {
            $siteId = Sites::getActive();
        }

        $labels = [];
        foreach ($shipmentIds as $shipmentId) {
            $response = self::client($siteId)
                ->get(self::baseUrl($siteId) . '/parcel/label/' . $shipmentId);

            if ($response->ok()) {
                $labels[$shipmentId] = $response->json('label');
            }
        }

        return ['labels' => $labels];
    }

    /**
     * Veloyd kent geen vaste enum voor pakkettype zoals MyParcel; we hanteren
     * een interne Dashed-mapping die hetzelfde voelt voor de admin. De waardes
     * mappen we onder water naar weight/dimensions in buildParcelPayload().
     */
    public static function getPackageTypes(): array
    {
        return [
            1 => 'Pakket',
            2 => 'Brievenbus post',
            3 => 'Brief',
            4 => 'Digitale stamp',
            5 => 'Pallet',
            6 => 'Klein pakket',
        ];
    }

    protected static function getPackageTypeDefaults(int $packageType): array
    {
        // grammen / millimeters — Veloyd verwacht beide
        return match ($packageType) {
            2 => ['weight' => 1000, 'length' => 380, 'width' => 265, 'height' => 32],
            3 => ['weight' => 100,  'length' => 230, 'width' => 165, 'height' => 5],
            4 => ['weight' => 50,   'length' => 200, 'width' => 140, 'height' => 1],
            5 => ['weight' => 30000, 'length' => 1200, 'width' => 800, 'height' => 1500],
            6 => ['weight' => 2000, 'length' => 350, 'width' => 250, 'height' => 80],
            default => ['weight' => 5000, 'length' => 350, 'width' => 250, 'height' => 200],
        };
    }

    public static function getBiggestPackageNeededByIds(string $region, array $packageTypeIds, string $siteId): int
    {
        if ((count($packageTypeIds) >= Customsetting::get('veloyd_minimum_product_count_' . $region, $siteId))
            && Customsetting::get('veloyd_minimum_product_count_package_type_' . $region, $siteId)) {
            return (int) Customsetting::get('veloyd_minimum_product_count_package_type_' . $region, $siteId);
        }

        if (in_array(5, $packageTypeIds)) {
            return 5;
        } elseif (in_array(4, $packageTypeIds)) {
            return 4;
        } elseif (in_array(1, $packageTypeIds)) {
            return 1;
        } elseif (in_array(6, $packageTypeIds)) {
            return 6;
        } elseif (in_array(2, $packageTypeIds)) {
            return 2;
        } elseif (in_array(3, $packageTypeIds)) {
            return 3;
        } else {
            return 3;
        }
    }

    /**
     * Dashed-zijdige labels voor de admin. Veloyd levert deze als dynamische
     * strings terug via /parcel/options; om hetzelfde UX-gevoel als MyParcel
     * te houden bieden we de admin een vaste set die we 1-op-1 naar Veloyd's
     * option-strings sturen.
     */
    public static function getDeliveryTypes(): array
    {
        return [
            'Standaard' => 'Standaard',
            'Spoed levering' => 'Spoed levering',
            'Verzekerd' => 'Verzekerd',
            'Afhaalpunt' => 'Pakket punt (afhaalpunt)',
            'Pick & Ship' => 'Ophaalservice (Pick & Ship)',
        ];
    }

    public static function getCarriers(): array
    {
        return [
            'PostNL' => 'PostNL',
            'DHL' => 'DHL',
            'DPD' => 'DPD',
            'GLS' => 'GLS',
            'Cycloon' => 'Cycloon',
            'Hurby' => 'Hurby',
            'Peddler' => 'Peddler',
            'Skynet' => 'Skynet',
        ];
    }

    /**
     * Combineert losse PDFs tot één bestand. Gebruikt ghostscript als die
     * beschikbaar is, valt anders terug op het eerste PDF zodat de admin
     * altijd iets bruikbaars krijgt — losse PDFs zijn naast deze samenvoeging
     * altijd los te downloaden per order vanuit het order-overzicht.
     */
    protected static function combinePdfs(array $paths): ?string
    {
        $paths = array_values(array_filter($paths));
        if (empty($paths)) {
            return null;
        }

        if (count($paths) === 1) {
            return $paths[0];
        }

        $combinedPath = 'dashed/orders/veloyd/labels-' . time() . '.pdf';
        $absoluteOut = Storage::disk('public')->path($combinedPath);
        @mkdir(dirname($absoluteOut), 0775, true);

        $gs = trim((string) shell_exec('command -v gs 2>/dev/null'));
        if ($gs !== '') {
            $absolutePaths = array_map(
                fn ($p) => escapeshellarg(Storage::disk('public')->path($p)),
                $paths
            );

            $cmd = $gs . ' -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite '
                . '-sOutputFile=' . escapeshellarg($absoluteOut) . ' '
                . implode(' ', $absolutePaths);

            @shell_exec($cmd);

            if (file_exists($absoluteOut)) {
                return $combinedPath;
            }
        }

        // Fallback: lever het eerste PDF terug — de losse labels staan via
        // hun individuele label_pdf_path nog steeds in de admin per order.
        return $paths[0];
    }
}
