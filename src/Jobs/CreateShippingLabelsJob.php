<?php

namespace Dashed\DashedEcommerceVeloyd\Jobs;

use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceVeloyd\Classes\Veloyd;
use Dashed\DashedEcommerceCore\Jobs\ExportSpecificPackingSlipsJob;

class CreateShippingLabelsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(): void
    {
        $response = $this->generateLabels();

        if (($response['processed'] ?? 0) > 0) {
            $notification = Notification::make()
                ->body('Labels zijn aangemaakt (' . count($response['orders']) . ' bestellingen)')
                ->persistent()
                ->success();

            if (! empty($response['filePath'])) {
                $notification->actions([
                    Action::make('download')
                        ->label('Download labels')
                        ->button()
                        ->url(Storage::disk('public')->url($response['filePath']))
                        ->openUrlInNewTab(),
                ]);
            }

            $notification->sendToDatabase($this->user)->send();

            ExportSpecificPackingSlipsJob::dispatch($response['orders'], $this->user)->onQueue('ecommerce');
        }

        if (self::shouldChainNextBatch($response)) {
            self::dispatch($this->user)->onQueue('ecommerce');
        }
    }

    protected function generateLabels(): array
    {
        return Veloyd::createShipments();
    }

    public static function shouldChainNextBatch(array $response): bool
    {
        return ($response['processed'] ?? 0) > 0 && ($response['hasMore'] ?? false);
    }
}
