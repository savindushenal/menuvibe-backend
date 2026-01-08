<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class DashboardController extends Controller
{
    /**
     * Get user from token manually
     */
    private function getUserFromToken(Request $request)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }

        $personalAccessToken = PersonalAccessToken::findToken($token);
        
        if (!$personalAccessToken) {
            return null;
        }

        return $personalAccessToken->tokenable;
    }

    /**
     * Get dashboard statistics
     */
    public function stats(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            // Get user's locations with menus
            $locations = $user->locations()->with(['menus.menuItems'])->get();
            
            // Calculate stats
            $totalLocations = $locations->count();
            $totalMenus = 0;
            $totalMenuItems = 0;
            $activeMenus = 0;
            
            foreach ($locations as $location) {
                $menus = $location->menus;
                $totalMenus += $menus->count();
                
                foreach ($menus as $menu) {
                    if ($menu->is_active ?? true) {
                        $activeMenus++;
                    }
                    $totalMenuItems += $menu->menuItems->count();
                }
            }

            // Get QR code scans count (mock data for now)
            $qrScansCount = $totalLocations * 150; // Mock: 150 scans per location average
            
            // Format stats for frontend
            $stats = [
                'totalViews' => [
                    'value' => $totalMenus * 250, // Mock: 250 views per menu
                    'change' => 12.5,
                    'trend' => 'up',
                    'formatted' => number_format($totalMenus * 250),
                ],
                'qrScans' => [
                    'value' => $qrScansCount,
                    'change' => 8.2,
                    'trend' => 'up',
                    'formatted' => number_format($qrScansCount),
                ],
                'menuItems' => [
                    'value' => $totalMenuItems,
                    'change' => $totalMenuItems > 0 ? 5 : 0,
                    'trend' => $totalMenuItems > 0 ? 'up' : 'down',
                    'formatted' => number_format($totalMenuItems),
                ],
                'activeCustomers' => [
                    'value' => $totalLocations * 45, // Mock: 45 customers per location
                    'change' => 15.3,
                    'trend' => 'up',
                    'formatted' => number_format($totalLocations * 45),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recentActivity' => $this->getRecentActivity($user),
                    'popularItems' => $this->getPopularItems($user),
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error('Dashboard stats error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return empty stats on error
            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'totalViews' => [
                            'value' => 0,
                            'change' => 0,
                            'trend' => 'down',
                            'formatted' => '0',
                        ],
                        'qrScans' => [
                            'value' => 0,
                            'change' => 0,
                            'trend' => 'down',
                            'formatted' => '0',
                        ],
                        'menuItems' => [
                            'value' => 0,
                            'change' => 0,
                            'trend' => 'down',
                            'formatted' => '0',
                        ],
                        'activeCustomers' => [
                            'value' => 0,
                            'change' => 0,
                            'trend' => 'down',
                            'formatted' => '0',
                        ],
                    ],
                    'recentActivity' => [],
                    'popularItems' => [],
                ],
            ], Response::HTTP_OK);
        }
    }

    /**
     * Get recent activity for user
     */
    private function getRecentActivity($user)
    {
        $activities = [];
        
        try {
            // Get 5 most recently updated menus
            $recentMenus = $user->locations()
                ->with(['menus' => function($query) {
                    $query->latest('updated_at')->limit(5);
                }])
                ->get()
                ->pluck('menus')
                ->flatten()
                ->filter() // Remove null values
                ->sortByDesc('updated_at')
                ->take(5);

            foreach ($recentMenus as $menu) {
                if ($menu && $menu->updated_at) {
                    $timeAgo = $this->getTimeAgo($menu->updated_at);
                    $activities[] = [
                        'description' => "Updated menu '{$menu->name}'",
                        'timeAgo' => $timeAgo,
                        'type' => 'menu_update',
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error getting recent activity', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
        
        // If no activities, add default
        if (empty($activities)) {
            $activities[] = [
                'description' => 'Welcome to your dashboard!',
                'timeAgo' => 'Just now',
                'type' => 'system',
            ];
        }

        return array_values($activities);
    }

    /**
     * Get popular menu items
     */
    private function getPopularItems($user)
    {
        $items = [];
        
        try {
            // Get menu items from user's locations
            $menuItems = $user->locations()
                ->with(['menus.menuItems'])
                ->get()
                ->pluck('menus')
                ->flatten()
                ->filter() // Remove null values
                ->pluck('menuItems')
                ->flatten()
                ->filter() // Remove null values
                ->take(5);

            foreach ($menuItems as $item) {
                if ($item && $item->id && $item->name) {
                    $items[] = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'viewCount' => rand(50, 500), // Mock view count
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error getting popular items', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }

        return $items;
    }

    /**
     * Convert datetime to human-readable "time ago" format
     */
    private function getTimeAgo($datetime)
    {
        $now = now();
        $diff = $now->diffInSeconds($datetime);

        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
    }
}
