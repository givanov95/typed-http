<?php

declare(strict_types=1);

namespace Givanov95\TypedHttp\Laravel;

use Givanov95\TypedHttp\Connector;
use Givanov95\TypedHttp\Contracts\Transport;
use Givanov95\TypedHttp\Transport\RetryClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

final class TypedHttpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/typed-http.php', 'typed-http');

        $this->app->bind(LaravelTransport::class, fn ($app) => new LaravelTransport($app->make(Factory::class)));

        $this->app->bind(Transport::class, function ($app) {
            $transport = $app->make(LaravelTransport::class);
            $retry = (array) $app['config']->get('typed-http.retry', []);

            if ((int) ($retry['times'] ?? 0) < 1) {
                return $transport;
            }

            return new RetryClient(
                $transport,
                times: (int) $retry['times'],
                delayMs: (int) ($retry['delay'] ?? 200),
                retryStatuses: array_map('intval', (array) ($retry['statuses'] ?? [429, 502, 503, 504])),
                retryUnsafe: (bool) ($retry['unsafe'] ?? false),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../../../config/typed-http.php' => $this->app->configPath('typed-http.php')], 'typed-http-config');

        Connector::useDefaultClient(fn () => $this->app->make(Transport::class));
    }
}
