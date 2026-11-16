<?php

namespace Dashed\DashedEcommerceVeloyd\Jobs;

use Throwable;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Auth;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\BackgroundTask;
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

    public const TASK_TYPE = 'veloyd-labels';

    public $tries = 5;
    public $timeout = 1200;

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(): void
    {
        $task = $this->task();

        // Zet de gebruiker die op "Download Veloyd labels" klikte tijdelijk
        // als ingelogd, zodat de orderlogs per label op zijn naam komen. Een
        // queue-worker leeft lang: daarna altijd weer vergeten.
        $previousUser = Auth::user();
        Auth::setUser($this->user);

        try {
            $response = $this->generateLabels();
        } finally {
            if ($previousUser) {
                Auth::setUser($previousUser);
            } elseif (method_exists(Auth::guard(), 'forgetUser')) {
                Auth::guard()->forgetUser();
            }
        }

        if (($response['processed'] ?? 0) > 0) {
            $notification = Notification::make()
                ->body(__('Labels zijn aangemaakt (:aantal bestellingen)', ['aantal' => count($response['orders'])]))
                ->persistent()
                ->success();

            if (! empty($response['filePath'])) {
                $notification->actions([
                    Action::make('download')
                        ->label(__('Download labels'))
                        ->button()
                        ->url(Storage::disk('public')->url($response['filePath']))
                        ->openUrlInNewTab(),
                ]);
            }

            $notification->sendToDatabase($this->user)->send();

            ExportSpecificPackingSlipsJob::dispatch($response['orders'], $this->user)->onQueue('ecommerce');
        }

        if (self::shouldChainNextBatch($response)) {
            // De volgende batch pakt dezelfde taak weer op.
            $task?->advance((int) ($response['processed'] ?? 0));

            self::dispatch($this->user)->onQueue('ecommerce');

            return;
        }

        $done = (int) ($task?->done ?? 0) + (int) ($response['processed'] ?? 0);
        $task?->progress($done)->finish($done > 0
            ? __(':aantal labels aangemaakt.', ['aantal' => $done])
            : __('Er stonden geen labels klaar.'));
    }

    /**
     * De taak in het takenicoon van dashed-core: één voor de hele reeks
     * batches. Zonder eigen melding, want handle() stuurt per batch al een
     * melding met de downloadlink.
     */
    protected function task(): ?BackgroundTask
    {
        if (! class_exists(BackgroundTask::class)) {
            return null;
        }

        return rescue(function () {
            $task = $this->activeTask();

            if ($task) {
                return $task;
            }

            $total = (int) rescue(fn () => Veloyd::unprintedCount(Sites::getActive()), 0, report: false);

            return BackgroundTask::begin(self::TASK_TYPE, __('Veloyd-labels aanmaken'), $total ?: null, $this->user, notify: false);
        }, null, report: false);
    }

    protected function activeTask(): ?BackgroundTask
    {
        return BackgroundTask::query()
            ->where('type', self::TASK_TYPE)
            ->where('user_id', $this->user->getKey())
            ->active()
            ->latest('id')
            ->first();
    }

    /**
     * Na de laatste poging: de taak mag niet op "Bezig" blijven staan.
     */
    public function failed(Throwable $e): void
    {
        if (class_exists(BackgroundTask::class)) {
            rescue(fn () => $this->activeTask()?->fail($e), report: false);
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
