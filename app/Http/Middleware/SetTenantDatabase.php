<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class SetTenantDatabase
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $tenant = null)
    {
        $user = auth()->user();
        
        if (!$user || !$user->franchise) {
            return $next($request);
        }

        $franchise = $user->franchise;
        $franchiseSlug = $tenant ?? $franchise->slug;
        
        // Get franchise config
        $config = config("franchise.{$franchiseSlug}", config('franchise.default'));
        $connectionName = $config['database_connection'] ?? config('database.default');
        
        // Set database connection for this request
        if ($connectionName !== config('database.default')) {
            Config::set('database.default', $connectionName);
            DB::purge('mysql'); // Clear default connection
            DB::reconnect($connectionName);
        }

        // Store franchise info in request for later use
        $request->attributes->set('franchise_slug', $franchiseSlug);
        $request->attributes->set('franchise_id', $franchise->id);

        return $next($request);
    }
}
