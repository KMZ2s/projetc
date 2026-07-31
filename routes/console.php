<?php

use App\Models\TrackingEventDelivery;
use App\Services\OrderTrackingDispatcher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tracking:retry-failed {--limit= : Maximum deliveries to inspect}', function () {
    $limit = max(1, min(
        500,
        (int) ($this->option('limit') ?: config('tracking.http.retry_batch_size', 100)),
    ));
    $maxAttempts = max(1, (int) config('tracking.http.max_delivery_attempts', 8));
    $staleBefore = now()->subSeconds(
        (int) config('tracking.http.processing_stale_after_seconds', 120),
    );

    $deliveries = TrackingEventDelivery::query()
        ->with('order')
        ->whereNotNull('order_id')
        ->where('attempts', '<', $maxAttempts)
        ->whereHas('integration', fn ($query) => $query
            ->active()
            ->where('server_enabled', true))
        ->where(function ($query) use ($staleBefore): void {
            $query
                ->where(function ($failed): void {
                    $failed
                        ->where('status', 'failed')
                        ->where(function ($transient): void {
                            $transient
                                ->whereNull('last_http_status')
                                ->orWhere('last_http_status', 408)
                                ->orWhere('last_http_status', 429)
                                ->orWhere('last_http_status', '>=', 500);
                        });
                })
                ->orWhere(function ($processing) use ($staleBefore): void {
                    $processing
                        ->where('status', 'processing')
                        ->where('updated_at', '<=', $staleBefore);
                });
        })
        ->oldest('updated_at')
        ->limit($limit)
        ->get();

    $retried = 0;

    foreach ($deliveries as $delivery) {
        if ($delivery->order === null) {
            continue;
        }

        try {
            app(OrderTrackingDispatcher::class)->dispatch(
                $delivery->order,
                $delivery->event_name,
            );
            $retried++;
        } catch (Throwable $exception) {
            Log::warning('Tracking retry command failed.', [
                'delivery_id' => (int) $delivery->getKey(),
                'exception' => $exception::class,
            ]);
        }
    }

    $this->info("Tracking deliveries inspected: {$retried}.");
})->purpose('Retry transient or interrupted server-side tracking deliveries');

Schedule::command('tracking:retry-failed')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
