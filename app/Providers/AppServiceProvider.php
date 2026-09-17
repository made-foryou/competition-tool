<?php

namespace App\Providers;

use App\Support\ErrorPageExit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureErrorPages();
    }

    /**
     * Laat de statussen die een bezoeker realistisch kan raken binnen de
     * Inertia-schil renderen, in plaats van op Laravel's kale vendor-pagina.
     */
    protected function configureErrorPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ?ExceptionResponse {
            $status = $response->statusCode();

            if (! in_array($status, [403, 404, 419, 500, 503], true)) {
                return null;
            }

            // Bij een serverfout is de stacktrace nuttiger dan de nette
            // schil, dus lokaal en in tests blijft 500/503 de standaard
            // foutpagina.
            if (in_array($status, [500, 503], true) && app()->environment(['local', 'testing'])) {
                return null;
            }

            return $response
                ->render('errors/error', [
                    'status' => $status,
                    ...(new ErrorPageExit)->for($response->request->user()),
                ])
                ->withSharedData();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventSilentlyDiscardingAttributes(
            ! app()->isProduction(),
        );

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
