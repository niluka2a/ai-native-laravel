<?php

namespace App\Providers;

use App\Contracts\ClassifierProvider;
use App\Services\KeywordClassifier;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ClassifierProvider::class => KeywordClassifier::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Single resources and plain collections render at the top level. Paginated
        // collections are still wrapped in "data" with links and meta.
        JsonResource::withoutWrapping();

        if (config('challenge.log_queries')) {
            DB::listen(function (QueryExecuted $query) {
                Log::channel('stderr')->debug('query: '.$query->sql);
            });
        }
    }
}
