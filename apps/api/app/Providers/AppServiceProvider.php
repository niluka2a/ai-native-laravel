<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (config('challenge.log_queries')) {
            DB::listen(function (QueryExecuted $query) {
                Log::channel('stderr')->debug('query: '.$query->sql);
            });
        }
    }
}
