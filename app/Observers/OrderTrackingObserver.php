<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\OrderTrackingDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderTrackingObserver
{
    public function updated(Order $order): void
    {
        $events = $this->eventsFor($order);

        foreach ($events as $event) {
            $this->schedule((int) $order->getKey(), $event);
        }
    }

    private function eventsFor(Order $order): array
    {
        $events = [];

        if ($order->wasChanged('payment_status')) {
            $event = match ((string) $order->payment_status) {
                'paid' => 'purchase',
                'failed', 'refused' => 'payment_refused',
                'refunded' => 'refund',
                'chargeback', 'chargedback' => 'chargeback',
                default => null,
            };

            if ($event !== null) {
                $events[] = $event;
            }
        }

        if ($order->wasChanged('status')) {
            $event = match ((string) $order->status) {
                'refunded' => 'refund',
                'chargeback', 'chargedback' => 'chargeback',
                default => null,
            };

            if ($event !== null) {
                $events[] = $event;
            }
        }

        if (
            $order->wasChanged('blackcat_transaction_id')
            && $order->payment_method === 'pix'
            && $order->payment_status === 'pending'
            && filled($order->blackcat_transaction_id)
        ) {
            $events[] = 'pix_generated';
        }

        return array_values(array_unique($events));
    }

    private function schedule(int $orderId, string $event): void
    {
        if (app()->runningUnitTests()) {
            self::enqueue($orderId, $event);

            return;
        }

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(static fn () => self::enqueue($orderId, $event));

            return;
        }

        self::enqueue($orderId, $event);
    }

    private static function enqueue(int $orderId, string $event): void
    {
        $deliver = static function () use ($orderId, $event): void {
            try {
                $order = Order::query()->find($orderId);

                if ($order !== null) {
                    app(OrderTrackingDispatcher::class)->dispatch($order, $event);
                }
            } catch (Throwable $exception) {
                Log::warning('Server-side tracking dispatch failed.', [
                    'order_id' => $orderId,
                    'event' => $event,
                    'exception' => $exception::class,
                ]);
            }
        };

        if (app()->runningInConsole()) {
            $deliver();

            return;
        }

        app()->terminating($deliver);
    }
}
