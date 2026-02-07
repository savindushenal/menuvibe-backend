<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Franchise;

class FranchiseBrandingMiddleware
{
    /**
     * Handle an incoming request.
     * 
     * Detects franchise from subdomain or custom domain and loads branding.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $franchise = $this->detectFranchise($request);
        
        if ($franchise) {
            // Store franchise in request for controllers to access
            $request->attributes->set('franchise', $franchise);
            $request->attributes->set('franchise_branding', $franchise->getBrandingData());
            
            // Add branding headers for frontend
            $response = $next($request);
            
            if ($response instanceof Response) {
                $response->headers->set('X-Franchise-Id', (string) $franchise->id);
                $response->headers->set('X-Franchise-Slug', $franchise->slug);
            }
            
            return $response;
        }
        
        return $next($request);
    }

    /**
     * Detect franchise from request.
     */
    protected function detectFranchise(Request $request): ?Franchise
    {
        // First, check for custom domain
        $host = $request->getHost();
        
        // Skip detection for main domain
        $mainDomains = [
            'MenuVire.com',
            'www.MenuVire.com',
            'api.MenuVire.com',
            'localhost',
            '127.0.0.1',
        ];
        
        if (in_array($host, $mainDomains)) {
            return null;
        }

        // Check for custom domain (e.g., menu.subway.com)
        $franchise = Franchise::active()
            ->where('custom_domain', $host)
            ->where('domain_verified', true)
            ->first();

        if ($franchise) {
            return $franchise;
        }

        // Check for subdomain (e.g., subway.MenuVire.com)
        $subdomain = $this->extractSubdomain($host);
        
        if ($subdomain) {
            return Franchise::active()
                ->where('slug', $subdomain)
                ->first();
        }

        return null;
    }

    /**
     * Extract subdomain from host.
     */
    protected function extractSubdomain(string $host): ?string
    {
        // Remove port if present
        $host = preg_replace('/:\d+$/', '', $host);
        
        // Define base domains
        $baseDomains = [
            'MenuVire.com',
            'MenuVire.local',
            'MenuVire.test',
        ];

        foreach ($baseDomains as $baseDomain) {
            if (str_ends_with($host, '.' . $baseDomain)) {
                $subdomain = str_replace('.' . $baseDomain, '', $host);
                
                // Ignore common subdomains
                if (!in_array($subdomain, ['www', 'api', 'app', 'admin'])) {
                    return $subdomain;
                }
            }
        }

        return null;
    }
}
