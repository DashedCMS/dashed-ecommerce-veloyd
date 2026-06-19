<?php

use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Eenmalige backfill: bestaande Veloyd-zendingen kregen door een bug geen
     * track & trace op de order bij het aanmaken van het label. Deze migratie
     * haalt de ontbrekende T&T alsnog op (via /parcel/get) en koppelt 'm aan de
     * order, zodat het overal waar we deployen automatisch wordt rechtgezet.
     *
     * Deploy-veilig: een onbereikbare/onbeschikbare Veloyd-API mag de migratie
     * nooit laten falen — de dagelijkse backfill-command pikt de rest later op.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_veloyd')) {
            return;
        }

        try {
            Veloyd::backfillMissingTrackAndTraces();
        } catch (\Throwable $e) {
            Log::warning('Veloyd track & trace backfill-migratie overgeslagen', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        // Data-backfill; niet terug te draaien.
    }
};
