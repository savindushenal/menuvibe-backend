<?php

namespace App\Http\Controllers;

use App\Models\MenuEndpoint;
use App\Models\MenuOffer;
use App\Services\MenuResolver;
use Illuminate\Http\Request;

class PublicMenuController extends Controller
{
    protected MenuResolver $menuResolver;

    public function __construct(MenuResolver $menuResolver)
    {
        $this->menuResolver = $menuResolver;
    }

    /**
     * Get menu by short code (for customer scanning QR)
     * 
     * Enhanced to support multi-menu selection:
     * - If menu_id parameter provided: serve that specific menu
     * - If multiple menus active at current time: return corridor payload
     * - If single menu active: return that menu data
     * - Fallback: return configured template menu
     */
    public function getMenu(Request $request, string $shortCode)
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)
            ->where('is_active', true)
            ->with(['template' => function ($q) {
                $q->where('is_active', true);
            }, 'location', 'franchise'])
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found or inactive'
            ], 404);
        }
        
        // For franchise menus, template might be null (uses franchise settings)
        if (!$endpoint->franchise && !$endpoint->template) {
            return response()->json([
                'success' => false,
                'message' => 'Menu configuration not found'
            ], 404);
        }

        // Record scan
        $endpoint->recordScan();

        // If menu_id parameter is provided, force serve that menu
        $requestedMenuId = $request->query('menu_id');
        if ($requestedMenuId && $endpoint->location) {
            $menu = $endpoint->location->menus()
                ->active()
                ->where('id', $requestedMenuId)
                ->first();
            
            if ($menu) {
                return $this->serveMenuResponse($endpoint, $menu);
            }
        }

        // Try to resolve active menus using MenuResolver if location exists
        if ($endpoint->location) {
            $resolution = $this->menuResolver->resolve($endpoint->location);

            // If multiple menus are active, return corridor payload
            if ($resolution['action'] === 'corridor' && $resolution['menus']->isNotEmpty()) {
                return $this->serveCorridorResponse($endpoint, $resolution);
            }

            // If single menu is active, serve that menu
            if ($resolution['action'] === 'redirect' && $resolution['menu']) {
                return $this->serveMenuResponse($endpoint, $resolution['menu']);
            }
        }

        // Fallback: serve the configured template menu (legacy behavior)
        return $this->serveTemplateMenuResponse($endpoint);
    }

    /**
     * Serve corridor response when multiple menus are active
     * Shows user a menu selection landing page
     */
    private function serveCorridorResponse(MenuEndpoint $endpoint, array $resolution)
    {
        return response()->json([
            'success' => true,
            'action' => 'corridor',
            'data' => [
                'location' => [
                    'id' => $endpoint->location->id,
                    'name' => $endpoint->location->name,
                    'branch_name' => $endpoint->location->branch_name,
                ],
                'endpoint' => [
                    'id' => $endpoint->id,
                    'type' => $endpoint->type,
                    'name' => $endpoint->identifier ?? 'Table ' . $endpoint->position,
                    'table_number' => $endpoint->identifier,
                ],
                'menus' => $resolution['menus'],
                'cache_ttl_seconds' => $resolution['cache_ttl_seconds'],
                'next_change_at' => $resolution['next_change_at']?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Serve a specific menu by Model
     */
    private function serveMenuResponse(MenuEndpoint $endpoint, \App\Models\Menu $menu)
    {
        // Fetch menu categories and items
        $categories = $menu->activeCategories()
            ->with(['items' => function ($q) {
                $q->where('is_available', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        // Build menu data structure
        $menuData = [
            'id' => $menu->id,
            'name' => $menu->name,
            'slug' => $menu->slug,
            'description' => $menu->description,
            'style' => $menu->style ?? 'premium',
            'currency' => $menu->currency,
            'categories' => $categories->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'image_url' => $cat->image_url,
                'items' => $cat->items->map(fn($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => $item->price,
                    'image_url' => $item->image_url,
                    'is_available' => $item->is_available,
                    'variations' => json_decode($item->variations, true) ?? [],
                ]),
            ]),
        ];

        $location = $endpoint->location;
        $businessProfile = $endpoint->user_id ? 
            \App\Models\BusinessProfile::where('user_id', $endpoint->user_id)->first() : null;

        return response()->json([
            'success' => true,
            'action' => 'redirect',
            'data' => [
                'menu' => $menuData,
                'location' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'branch_name' => $location->branch_name,
                    'phone' => $location->phone,
                    'email' => $location->email,
                ],
                'business' => $businessProfile ? [
                    'name' => $businessProfile->business_name,
                    'logo_url' => $businessProfile->logo_url ?? $location->logo_url,
                    'primary_color' => $businessProfile->primary_color ?? $location->primary_color,
                    'secondary_color' => $businessProfile->secondary_color ?? $location->secondary_color,
                ] : null,
                'endpoint' => [
                    'id' => $endpoint->id,
                    'type' => $endpoint->type,
                    'name' => $endpoint->identifier ?? 'Table',
                ],
            ],
        ]);
    }

    /**
     * Serve template menu (legacy fallback behavior)
     */
    private function serveTemplateMenuResponse(MenuEndpoint $endpoint)
    {
        // Get menu with overrides applied
        $menu = $endpoint->getMenuWithOverrides();

        // Get active offers
        $offers = $this->getActiveOffersForEndpoint($endpoint);

        // Build business info from BusinessProfile + Location (branch)
        $business = null;
        $businessProfile = null;
        $location = $endpoint->location;
        
        // Get business profile from user
        if ($endpoint->user_id) {
            $businessProfile = \App\Models\BusinessProfile::where('user_id', $endpoint->user_id)->first();
        }
        
        // If no direct location, try to get user's default location
        if (!$location && $endpoint->user_id) {
            $location = \App\Models\Location::where('user_id', $endpoint->user_id)
                ->where('is_default', true)
                ->first();
            
            // If no default, get first location
            if (!$location) {
                $location = \App\Models\Location::where('user_id', $endpoint->user_id)->first();
            }
        }
        
        // Build business object with business_name from BusinessProfile and branch_name from Location
        if ($businessProfile || $location) {
            $business = [
                // Business name from BusinessProfile, fallback to location name
                'name' => $businessProfile?->business_name ?? $location?->name ?? $endpoint->template?->name,
                // Branch/location name (for display like "Business Name - Branch Name")
                'branch_name' => $location?->name ?? null,
                'description' => $businessProfile?->description ?? $location?->description,
                'logo_url' => $businessProfile?->logo_url ?? $location?->logo_url,
                'phone' => $location?->phone ?? $businessProfile?->phone,
                'email' => $location?->email ?? $businessProfile?->email,
                'website' => $location?->website ?? $businessProfile?->website,
                'address' => $location ? array_filter([
                    $location->address_line_1,
                    $location->address_line_2,
                    $location->city,
                    $location->state,
                    $location->postal_code,
                    $location->country
                ]) : [],
                'cuisine_type' => $businessProfile?->cuisine_type ?? $location?->cuisine_type,
                'operating_hours' => $location?->operating_hours ?? $businessProfile?->operating_hours,
                'services' => $location?->services ?? $businessProfile?->services,
                'social_media' => $businessProfile?->social_media ?? $location?->social_media,
                'primary_color' => $businessProfile?->primary_color ?? $location?->primary_color,
                'secondary_color' => $businessProfile?->secondary_color ?? $location?->secondary_color,
            ];
        }

        // Build template configuration for API-driven rendering
        $templateConfig = $this->buildTemplateConfiguration($endpoint, $businessProfile, $location);

        // Build response data
        $data = [
            'menu' => $menu,
            'offers' => $offers,
            'business' => $business,
            'endpoint' => [
                'id' => $endpoint->id,
                'type' => $endpoint->type,
                'name' => $endpoint->display_name,
                'identifier' => $endpoint->identifier,
            ],
        ];

        // Add franchise data if this is a franchise endpoint
        if ($endpoint->franchise) {
            $data['franchise'] = [
                'id' => $endpoint->franchise->id,
                'name' => $endpoint->franchise->name,
                'slug' => $endpoint->franchise->slug,
                'logo_url' => $endpoint->franchise->logo_url,
                'design_tokens' => $endpoint->franchise->design_tokens,
                'template_type' => $endpoint->franchise->template_type ?? 'premium',
            ];
        }

        // Add template data if exists (business menus always have template)
        if ($endpoint->template) {
            $data['template'] = [
                'id' => $endpoint->template->id,
                'name' => $endpoint->template->name,
                'type' => $endpoint->template->settings['template_type'] ?? 'premium',
                'currency' => $endpoint->template->currency,
                'settings' => $endpoint->template->settings,
                'image_url' => $endpoint->template->image_url,
                // New: API-driven configuration
                'config' => $templateConfig,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get only menu data (lighter response)
     */
    public function getMenuOnly(Request $request, string $shortCode)
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)
            ->where('is_active', true)
            ->with(['template' => function ($q) {
                $q->where('is_active', true);
            }])
            ->first();

        if (!$endpoint || !$endpoint->template) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found or inactive'
            ], 404);
        }

        // Get menu with overrides applied
        $menu = $endpoint->getMenuWithOverrides();

        return response()->json([
            'success' => true,
            'data' => $menu
        ]);
    }

    /**
     * Get active offers for an endpoint
     */
    public function getOffers(Request $request, string $shortCode)
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)
            ->where('is_active', true)
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $offers = $this->getActiveOffersForEndpoint($endpoint);

        return response()->json([
            'success' => true,
            'data' => $offers
        ]);
    }

    /**
     * Record a scan (for analytics)
     */
    public function recordScan(Request $request, string $shortCode)
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        $endpoint->recordScan();

        return response()->json([
            'success' => true,
            'message' => 'Scan recorded'
        ]);
    }
    
    /**
     * Get complete configuration (API-driven approach)
     * This endpoint returns everything needed for the frontend to render dynamically
     */
    public function getConfig(Request $request, string $shortCode)
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)
            ->where('is_active', true)
            ->with(['template' => function ($q) {
                $q->where('is_active', true);
            }, 'location'])
            ->first();

        if (!$endpoint || !$endpoint->template) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found or inactive'
            ], 404);
        }

        // Get business profile
        $businessProfile = null;
        if ($endpoint->user_id) {
            $businessProfile = \App\Models\BusinessProfile::where('user_id', $endpoint->user_id)->first();
        }

        $location = $endpoint->location;
        if (!$location && $endpoint->user_id) {
            $location = \App\Models\Location::where('user_id', $endpoint->user_id)
                ->where('is_default', true)
                ->first();
        }

        // Build complete configuration
        $templateConfig = $this->buildTemplateConfiguration($endpoint, $businessProfile, $location);
        
        // Add URLs for resources
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $apiUrl = config('app.url', 'http://localhost:8000');
        
        $templateConfig['resources'] = [
            'menu_url' => $frontendUrl . '/m/' . $shortCode,
            'api_url' => $apiUrl . '/api/public/menu/' . $shortCode,
            'qr_url' => $apiUrl . '/api/qr/' . $shortCode,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'endpoint' => [
                    'id' => $endpoint->id,
                    'type' => $endpoint->type,
                    'name' => $endpoint->display_name,
                    'identifier' => $endpoint->identifier,
                    'template_key' => $endpoint->template_key ?? 'default',
                ],
                'template' => [
                    'id' => $endpoint->template->id,
                    'name' => $endpoint->template->name,
                    'currency' => $endpoint->template->currency,
                ],
                'config' => $templateConfig,
            ]
        ]);
    }

    /**
     * Get endpoint info (for preview)
     */
    public function getEndpointInfo(Request $request, string $shortCode)
    {
        $endpoint = MenuEndpoint::where('short_code', $shortCode)
            ->with('template:id,name,image_url,currency')
            ->first();

        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $endpoint->id,
                'type' => $endpoint->type,
                'type_name' => $endpoint->type_name,
                'name' => $endpoint->display_name,
                'identifier' => $endpoint->identifier,
                'is_active' => $endpoint->is_active,
                'template' => $endpoint->template ? [
                    'id' => $endpoint->template->id,
                    'name' => $endpoint->template->name,
                    'image_url' => $endpoint->template->image_url,
                    'currency' => $endpoint->template->currency,
                ] : null,
            ]
        ]);
    }

    /**
     * Build template configuration for API-driven rendering
     * This allows frontend to be a "dumb renderer" controlled by API
     */
    private function buildTemplateConfiguration($endpoint, $businessProfile, $location): array
    {
        // Get template settings
        $settings = $endpoint->template->settings ?? [];
        $layout = $settings['layout'] ?? 'standard';
        $colorTheme = $settings['colorTheme'] ?? 'modern';
        
        // Build design variables from business branding
        $designVariables = [
            'colors' => [
                'primary' => $businessProfile?->primary_color ?? '#3B82F6',
                'secondary' => $businessProfile?->secondary_color ?? '#1E293B',
                'background' => '#FFFFFF',
                'text' => '#1E293B',
                'accent' => $businessProfile?->primary_color ?? '#3B82F6',
            ],
            'typography' => [
                'fontFamily' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
                'headingWeight' => 700,
                'bodyWeight' => 400,
            ],
            'spacing' => [
                'container' => '1rem',
                'section' => '1.5rem',
                'card' => '1rem',
            ],
            'borderRadius' => [
                'small' => '0.5rem',
                'medium' => '0.75rem',
                'large' => '1rem',
            ],
        ];
        
        // Define component structure based on layout
        $components = $this->getComponentsForLayout($layout);
        
        // Build feature flags
        $features = [
            'cart' => true,
            'search' => false,
            'filters' => false,
            'loyalty' => false, // Can be enabled per business
            'ordering' => false,
            'payment' => false,
        ];
        
        // Business rules (can be customized per business)
        $businessRules = [
            'priceModifiers' => [], // Time-based pricing, etc.
            'availability' => [
                'check_inventory' => false,
                'real_time_sync' => false,
            ],
            'ordering' => [
                'enabled' => false,
                'min_order_amount' => 0,
                'delivery_enabled' => false,
                'pickup_enabled' => false,
            ],
        ];
        
        return [
            'version' => '1.0.0',
            'layout' => $layout,
            'colorTheme' => $colorTheme,
            'design' => $designVariables,
            'components' => $components,
            'features' => $features,
            'businessRules' => $businessRules,
        ];
    }
    
    /**
     * Get component configuration based on layout type
     */
    private function getComponentsForLayout(string $layout): array
    {
        $baseComponents = [
            [
                'id' => 'header',
                'type' => 'Header',
                'enabled' => true,
                'props' => [
                    'showLogo' => true,
                    'showCart' => true,
                    'showSearch' => false,
                ],
            ],
            [
                'id' => 'menu',
                'type' => 'MenuList',
                'enabled' => true,
                'props' => [
                    'showImages' => true,
                    'showDescriptions' => true,
                    'showPrices' => true,
                ],
            ],
            [
                'id' => 'cart',
                'type' => 'CartSheet',
                'enabled' => true,
                'props' => [
                    'position' => 'bottom',
                ],
            ],
        ];
        
        // Customize based on layout
        switch ($layout) {
            case 'premium':
                $baseComponents[] = [
                    'id' => 'featured',
                    'type' => 'FeaturedItems',
                    'enabled' => true,
                    'props' => [
                        'maxItems' => 6,
                    ],
                ];
                break;
                
            case 'minimal':
                // Minimal layout - remove some components
                $baseComponents = array_filter($baseComponents, function($c) {
                    return $c['id'] !== 'featured';
                });
                break;
        }
        
        return array_values($baseComponents);
    }
    
    /**
     * Get active offers for an endpoint
     */
    private function getActiveOffersForEndpoint(MenuEndpoint $endpoint): array
    {
        $offers = MenuOffer::where('user_id', $endpoint->user_id)
            ->where(function ($q) use ($endpoint) {
                $q->whereNull('template_id')
                  ->orWhere('template_id', $endpoint->template_id);
            })
            ->valid()
            ->orderBy('is_featured', 'desc')
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($offer) use ($endpoint) {
                return $offer->appliesToEndpoint($endpoint->id);
            })
            ->values()
            ->map(function ($offer) {
                return [
                    'id' => $offer->id,
                    'type' => $offer->offer_type,
                    'title' => $offer->title,
                    'description' => $offer->description,
                    'image_url' => $offer->image_url,
                    'badge_text' => $offer->badge_text ?? $offer->type_badge['text'],
                    'badge_color' => $offer->badge_color ?? $offer->type_badge['color'],
                    'discount_type' => $offer->discount_type,
                    'discount_value' => $offer->discount_value,
                    'bundle_price' => $offer->bundle_price,
                    'minimum_order' => $offer->minimum_order,
                    'remaining_time' => $offer->remaining_time,
                    'terms_conditions' => $offer->terms_conditions,
                    'is_featured' => $offer->is_featured,
                ];
            })
            ->toArray();

        return $offers;
    }
}
