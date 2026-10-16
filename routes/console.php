<?php

use App\Jobs\ReconcilePendingPaymentsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('court:expire-invitations')->hourly();

/*
|--------------------------------------------------------------------------
| Payment processing on shared hosting (no persistent worker, no SSH cron)
|--------------------------------------------------------------------------
| A single hPanel cron entry runs `php artisan schedule:run` every minute.
| Everything below is driven by that one entry:
|
|  1. Drain the RabbitMQ payment queue: run a worker that processes whatever
|     is waiting and then EXITS (--stop-when-empty). --max-time caps it well
|     under a minute so it never turns into a daemon and never overlaps the
|     next run. This gives up to ~1 min latency on webhook settlement.
|
|  2. Reconcile: poll the gateway for still-pending charges and settle any
|     whose webhook was lost. Run inline (not dispatched) so it doesn't create
|     another queue that would also need draining.
*/
Schedule::command('queue:work rabbitmq --queue=' . config('queue.connections.rabbitmq.queue', 'payments') . ' --stop-when-empty --max-time=50 --tries=5 --sleep=1')
    ->name('drain-payments-queue')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::call(fn () => dispatch_sync(new ReconcilePendingPaymentsJob()))
    ->name('reconcile-pending-payments')
    ->everyFiveMinutes()
    ->withoutOverlapping();
