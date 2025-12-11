<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\FranchiseContextController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\MenuCategoryController;
use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminActivityController;
use App\Http\Controllers\Admin\AdminFranchiseController;
use App\Http\Controllers\Admin\AdminFranchiseOnboardingController;
use App\Http\Controllers\MasterMenuController;
use App\Http\Controllers\MasterMenuOfferController;
use App\Http\Controllers\MenuTemplateController;
use App\Http\Controllers\MenuEndpointController;
use App\Http\Controllers\MenuOfferController;
use App\Http\Controllers\PublicMenuController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Health check
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is working',
        'timestamp' => now(),
        'app_name' => config('app.name', 'Laravel')
    ]);
});

// Google OAuth routes
Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);
Route::post('/auth/google', [SocialAuthController::class, 'googleAuth']);

// Manual auth routes (bypass EnsureFrontendRequestsAreStateful middleware)
Route::get('/user', [AuthController::class, 'profileManual']);
Route::get('/auth/contexts', [AuthController::class, 'getContexts']);
Route::get('/business-profile', [BusinessProfileController::class, 'indexManual']);
Route::post('/business-profile', [BusinessProfileController::class, 'storeManual']);
Route::put('/business-profile', [BusinessProfileController::class, 'updateManual']);
Route::post('/business-profile/complete-onboarding', [BusinessProfileController::class, 'completeOnboardingManual']);
Route::post('/logout', [AuthController::class, 'logoutManual']);
Route::post('/logout-all', [AuthController::class, 'logoutAllManual']);

// Menu management routes (manual auth)
Route::apiResource('menus', MenuController::class);
Route::get('/menus/{menu}/limits', [MenuItemController::class, 'checkLimits']);
Route::put('/menus/{menu}/items/reorder', [MenuItemController::class, 'reorder']);
Route::apiResource('menus.items', MenuItemController::class);
Route::get('/menus/{menu}/categories', [MenuCategoryController::class, 'index']);
Route::post('/menus/{menu}/categories', [MenuCategoryController::class, 'store']);
Route::put('/menus/{menu}/categories/{category}', [MenuCategoryController::class, 'update']);
Route::delete('/menus/{menu}/categories/{category}', [MenuCategoryController::class, 'destroy']);
Route::post('/menus/{menu}/categories/reorder', [MenuCategoryController::class, 'reorder']);

// Location management routes (manual auth)
Route::apiResource('locations', LocationController::class);
Route::post('/locations/{location}/set-default', [LocationController::class, 'setDefault']);
Route::put('/locations/sort-order', [LocationController::class, 'updateSortOrder']);
Route::get('/locations/{location}/statistics', [LocationController::class, 'statistics']);

// QR Code routes (manual auth)
Route::get('/qr-codes', [QRCodeController::class, 'index']);
Route::post('/qr-codes', [QRCodeController::class, 'store']);
Route::delete('/qr-codes/{id}', [QRCodeController::class, 'destroy']);

// Location-aware menu routes
Route::apiResource('locations.menus', MenuController::class);
Route::apiResource('locations.menus.items', MenuItemController::class);

// Subscription routes (manual auth)
Route::get('/subscription-plans', [SubscriptionController::class, 'getPlans']);
Route::get('/subscription/current', [SubscriptionController::class, 'getCurrentSubscription']);
Route::post('/subscription/trial/{planId}', [SubscriptionController::class, 'startTrial']);
Route::get('/subscription/recommendations', [SubscriptionController::class, 'getUpgradeRecommendations']);

// Public platform settings
Route::get('/platform/settings', [AdminSettingsController::class, 'publicSettings']);

/*
|--------------------------------------------------------------------------
| Franchise Context Routes (Slug-based)
|--------------------------------------------------------------------------
| Routes for franchise users to access their franchise context.
| Uses franchise slug in URL: /franchise/{slug}/dashboard
| All routes require authentication and franchise access verification.
*/
Route::prefix('franchise/{franchiseSlug}')
    ->middleware(['auth:sanctum', 'franchise'])
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', [FranchiseContextController::class, 'dashboard']);
        
        // Branches
        Route::get('/branches', [FranchiseContextController::class, 'branches']);
        
        // Locations
        Route::get('/locations', [FranchiseContextController::class, 'locations']);
        
        // Menus
        Route::get('/menus', [FranchiseContextController::class, 'menus']);
        
        // Staff/Team
        Route::get('/staff', [FranchiseContextController::class, 'staff']);
        
        // Settings
        Route::get('/settings', [FranchiseContextController::class, 'settings']);
    });

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
| Routes for admin and super admin users to manage the platform.
| All routes require authentication and admin role.
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/analytics', [AdminDashboardController::class, 'analytics']);
    
    // User Management
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::post('/users/{id}/toggle-status', [AdminUserController::class, 'toggleStatus']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
    Route::post('/users/{id}/reset-password', [AdminUserController::class, 'resetPassword']);
    Route::post('/admins', [AdminUserController::class, 'createAdmin']);
    
    // Platform Settings (super admin only)
    Route::get('/settings', [AdminSettingsController::class, 'index']);
    Route::get('/settings/group/{group}', [AdminSettingsController::class, 'showGroup']);
    Route::put('/settings/{key}', [AdminSettingsController::class, 'update']);
    Route::post('/settings/bulk', [AdminSettingsController::class, 'bulkUpdate']);
    
    // Subscription Management
    Route::get('/subscription-plans', [AdminSubscriptionController::class, 'plans']);
    Route::put('/subscription-plans/{id}', [AdminSubscriptionController::class, 'updatePlan']);
    Route::get('/subscriptions', [AdminSubscriptionController::class, 'subscriptions']);
    Route::post('/users/{userId}/subscription', [AdminSubscriptionController::class, 'changeUserSubscription']);
    Route::post('/subscriptions/{id}/cancel', [AdminSubscriptionController::class, 'cancelSubscription']);
    Route::get('/subscriptions/statistics', [AdminSubscriptionController::class, 'statistics']);
    
    // Support Tickets
    Route::get('/tickets', [AdminSupportController::class, 'index']);
    Route::get('/tickets/statistics', [AdminSupportController::class, 'statistics']);
    Route::get('/tickets/{id}', [AdminSupportController::class, 'show']);
    Route::post('/tickets/{id}/assign', [AdminSupportController::class, 'assign']);
    Route::post('/tickets/{id}/status', [AdminSupportController::class, 'updateStatus']);
    Route::post('/tickets/{id}/priority', [AdminSupportController::class, 'updatePriority']);
    Route::post('/tickets/{id}/messages', [AdminSupportController::class, 'addMessage']);
    
    // Activity Logs
    Route::get('/activity', [AdminActivityController::class, 'index']);
    Route::get('/activity/actions', [AdminActivityController::class, 'actions']);
    Route::get('/activity/admins', [AdminActivityController::class, 'admins']);
    Route::get('/activity/{id}', [AdminActivityController::class, 'show']);
    Route::get('/activity/target/{type}/{id}', [AdminActivityController::class, 'forTarget']);
    
    // Franchise Management (super admin only)
    Route::get('/franchises', [AdminFranchiseController::class, 'index']);
    Route::get('/franchises/statistics', [AdminFranchiseController::class, 'statistics']);
    Route::get('/franchises/{id}', [AdminFranchiseController::class, 'show']);
    Route::put('/franchises/{id}', [AdminFranchiseController::class, 'update']);
    Route::post('/franchises/{id}/toggle-status', [AdminFranchiseController::class, 'toggleStatus']);
    Route::post('/franchises/{id}/transfer-ownership', [AdminFranchiseController::class, 'transferOwnership']);
    Route::delete('/franchises/{id}', [AdminFranchiseController::class, 'destroy']);
    
    // Franchise Onboarding & Management (super admin only)
    Route::post('/franchises/onboard', [AdminFranchiseOnboardingController::class, 'onboard']);
    Route::get('/franchises/{id}/details', [AdminFranchiseOnboardingController::class, 'getFranchiseDetails']);
    Route::post('/franchises/{id}/accounts', [AdminFranchiseOnboardingController::class, 'createAccount']);
    Route::post('/franchises/{id}/invitations', [AdminFranchiseOnboardingController::class, 'sendInvitation']);
    Route::post('/franchises/{id}/branches', [AdminFranchiseOnboardingController::class, 'addBranch']);
    Route::post('/franchises/{id}/payments', [AdminFranchiseOnboardingController::class, 'recordPayment']);
    Route::put('/franchises/{franchiseId}/payments/{paymentId}', [AdminFranchiseOnboardingController::class, 'updatePayment']);
    Route::get('/franchises/{id}/branches', [AdminFranchiseOnboardingController::class, 'getBranches']);
    Route::get('/franchises/{id}/payments', [AdminFranchiseOnboardingController::class, 'getPayments']);
    Route::get('/franchises/{id}/accounts', [AdminFranchiseOnboardingController::class, 'getAccounts']);
    Route::get('/franchises/{id}/invitations', [AdminFranchiseOnboardingController::class, 'getInvitations']);
    Route::put('/franchises/{id}/pricing', [AdminFranchiseOnboardingController::class, 'updatePricing']);
    Route::put('/franchises/{franchiseId}/branches/{branchId}', [AdminFranchiseOnboardingController::class, 'updateBranch']);
    Route::delete('/franchises/{franchiseId}/branches/{branchId}', [AdminFranchiseOnboardingController::class, 'deleteBranch']);
    Route::post('/franchises/{franchiseId}/invitations/{invitationId}/resend', [AdminFranchiseOnboardingController::class, 'resendInvitation']);
    Route::delete('/franchises/{franchiseId}/invitations/{invitationId}', [AdminFranchiseOnboardingController::class, 'cancelInvitation']);
});

// Protected routes (with sanctum middleware for cookie-based auth)
Route::middleware('auth:sanctum')->group(function () {
    // Route::get('/user', [AuthController::class, 'profile']); // Temporarily disabled due to view config issue
    
    // Additional protected routes can be added here
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        
        // Get user's location IDs
        $locationIds = $user->locations()->pluck('id')->toArray();
        
        // Get actual counts from database
        $totalMenus = 0;
        $totalItems = 0;
        
        if (!empty($locationIds)) {
            $totalMenus = \App\Models\Menu::whereIn('location_id', $locationIds)->count();
            $totalItems = \App\Models\MenuItem::whereHas('menu', function($q) use ($locationIds) {
                $q->whereIn('location_id', $locationIds);
            })->count();
        }
        
        // For now, using mock data for views and scans (can be implemented with analytics later)
        return response()->json([
            'success' => true,
            'message' => 'Dashboard data',
            'data' => [
                'user' => $user,
                'stats' => [
                    'totalViews' => [
                        'value' => 0,
                        'formatted' => '0',
                        'change' => 0,
                        'trend' => 'neutral'
                    ],
                    'qrScans' => [
                        'value' => 0,
                        'formatted' => '0',
                        'change' => 0,
                        'trend' => 'neutral'
                    ],
                    'menuItems' => [
                        'value' => $totalItems,
                        'formatted' => (string)$totalItems,
                        'change' => 0,
                        'trend' => 'neutral'
                    ],
                    'activeCustomers' => [
                        'value' => 0,
                        'formatted' => '0',
                        'change' => 0,
                        'trend' => 'neutral'
                    ]
                ],
                'recentActivity' => [],
                'popularItems' => []
            ]
        ]);
    });
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'service' => 'MenuVibe API'
    ]);
});

// Test token endpoint without middleware
Route::get('/test-token', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'No token provided'], 401);
    }
    
    try {
        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        
        $user = $personalAccessToken->tokenable;
        return response()->json([
            'success' => true,
            'user' => $user,
            'token_valid' => true
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Test user profile without Sanctum middleware
Route::get('/test-profile', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'No token provided'], 401);
    }
    
    try {
        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        
        $user = $personalAccessToken->tokenable;
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Temporary working user endpoint to replace /api/user
Route::get('/user', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'No token provided'], 401);
    }
    
    try {
        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        
        $user = $personalAccessToken->tokenable;
        $user->load('businessProfile'); // Load business profile relationship
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'needs_onboarding' => $user->needsOnboarding()
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Temporary business profile endpoints with manual auth
Route::get('/business-profile', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'No token provided'], 401);
    }
    
    try {
        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        
        $user = $personalAccessToken->tokenable;
        $businessProfile = $user->businessProfile;

        if (!$businessProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Business profile not found',
                'needs_onboarding' => true
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'business_profile' => $businessProfile,
                'needs_onboarding' => !$businessProfile->isOnboardingCompleted()
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::post('/business-profile', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'No token provided'], 401);
    }
    
    try {
        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        
        $user = $personalAccessToken->tokenable;
        
        // Use the controller logic
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        $controller = new \App\Http\Controllers\BusinessProfileController();
        return $controller->store($request);
        
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Settings routes (manual auth)
Route::get('/settings', function (Request $request) {
    try {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = $personalAccessToken->tokenable;
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new \App\Http\Controllers\Api\SettingsController();
        return $controller->index($request);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::put('/settings/account', function (Request $request) {
    try {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = $personalAccessToken->tokenable;
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new \App\Http\Controllers\Api\SettingsController();
        return $controller->updateAccount($request);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::put('/settings/notifications', function (Request $request) {
    try {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = $personalAccessToken->tokenable;
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new \App\Http\Controllers\Api\SettingsController();
        return $controller->updateNotifications($request);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::put('/settings/security', function (Request $request) {
    try {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = $personalAccessToken->tokenable;
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new \App\Http\Controllers\Api\SettingsController();
        return $controller->updateSecurity($request);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::put('/settings/privacy', function (Request $request) {
    try {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = $personalAccessToken->tokenable;
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new \App\Http\Controllers\Api\SettingsController();
        return $controller->updatePrivacy($request);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::put('/settings/display', function (Request $request) {
    try {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = $personalAccessToken->tokenable;
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new \App\Http\Controllers\Api\SettingsController();
        return $controller->updateDisplay($request);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::put('/settings/business', function (Request $request) {
    try {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = $personalAccessToken->tokenable;
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new \App\Http\Controllers\Api\SettingsController();
        return $controller->updateBusiness($request);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::post('/settings/reset', function (Request $request) {
    try {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$personalAccessToken) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $user = $personalAccessToken->tokenable;
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new \App\Http\Controllers\Api\SettingsController();
        return $controller->resetToDefaults($request);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// ============================================
// FRANCHISE WHITE-LABEL ROUTES
// ============================================

use App\Http\Controllers\Api\FranchiseController;
use App\Http\Controllers\Api\FranchiseUserController;

// Public branding endpoint (no auth required)
Route::get('/branding/{identifier}', [FranchiseController::class, 'getBranding']);

// Public franchise endpoints for customer-facing menu view
Route::prefix('public/franchise')->group(function () {
    Route::get('/{slug}', [FranchiseController::class, 'getPublicFranchise']);
    Route::get('/{franchiseSlug}/location/{locationSlug}/menu', [FranchiseController::class, 'getPublicMenu']);
});

// Franchise invitation acceptance (can be public or auth based on token)
Route::post('/franchise-invitations/accept', [FranchiseUserController::class, 'acceptInvitation']);

// Franchise management routes (with manual token auth)
Route::get('/franchises', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseController::class)->index($request);
});

Route::post('/franchises', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseController::class)->store($request);
});

Route::get('/franchises/{id}', function (Request $request, int $id) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseController::class)->show($request, $id);
});

Route::put('/franchises/{id}', function (Request $request, int $id) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseController::class)->update($request, $id);
});

Route::delete('/franchises/{id}', function (Request $request, int $id) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseController::class)->destroy($request, $id);
});

// Franchise domain verification
Route::get('/franchises/{id}/domain-verification', function (Request $request, int $id) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseController::class)->getDomainVerification($request, $id);
});

Route::post('/franchises/{id}/verify-domain', function (Request $request, int $id) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseController::class)->verifyDomain($request, $id);
});

// Franchise locations management
Route::get('/franchises/{id}/locations', function (Request $request, int $id) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseController::class)->getLocations($request, $id);
});

Route::post('/franchises/{id}/locations', function (Request $request, int $id) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseController::class)->attachLocation($request, $id);
});

Route::delete('/franchises/{id}/locations/{locationId}', function (Request $request, int $id, int $locationId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseController::class)->detachLocation($request, $id, $locationId);
});

// Franchise users management
Route::get('/franchises/{franchiseId}/users', function (Request $request, int $franchiseId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseUserController::class)->index($request, $franchiseId);
});

Route::post('/franchises/{franchiseId}/users/invite', function (Request $request, int $franchiseId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseUserController::class)->invite($request, $franchiseId);
});

Route::put('/franchises/{franchiseId}/users/{userId}', function (Request $request, int $franchiseId, int $userId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseUserController::class)->update($request, $franchiseId, $userId);
});

Route::delete('/franchises/{franchiseId}/users/{userId}', function (Request $request, int $franchiseId, int $userId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseUserController::class)->remove($request, $franchiseId, $userId);
});

Route::post('/franchises/{franchiseId}/leave', function (Request $request, int $franchiseId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(FranchiseUserController::class)->leave($request, $franchiseId);
});

/*
|--------------------------------------------------------------------------
| Master Menu Routes (Franchise)
|--------------------------------------------------------------------------
| Routes for franchise master menu management.
| Allows franchises to create centralized menus that sync to all branches.
| Supports special offers, instant offers, and seasonal promotions.
*/

// Master Menu CRUD
Route::get('/franchises/{franchiseId}/master-menus', function (Request $request, int $franchiseId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->index($request, $franchiseId);
});

Route::post('/franchises/{franchiseId}/master-menus', function (Request $request, int $franchiseId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->store($request, $franchiseId);
});

Route::get('/franchises/{franchiseId}/master-menus/{menuId}', function (Request $request, int $franchiseId, int $menuId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->show($request, $franchiseId, $menuId);
});

Route::put('/franchises/{franchiseId}/master-menus/{menuId}', function (Request $request, int $franchiseId, int $menuId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->update($request, $franchiseId, $menuId);
});

Route::delete('/franchises/{franchiseId}/master-menus/{menuId}', function (Request $request, int $franchiseId, int $menuId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->destroy($request, $franchiseId, $menuId);
});

// Master Menu Categories
Route::post('/franchises/{franchiseId}/master-menus/{menuId}/categories', function (Request $request, int $franchiseId, int $menuId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->storeCategory($request, $franchiseId, $menuId);
});

Route::put('/franchises/{franchiseId}/master-menus/{menuId}/categories/{categoryId}', function (Request $request, int $franchiseId, int $menuId, int $categoryId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->updateCategory($request, $franchiseId, $menuId, $categoryId);
});

Route::delete('/franchises/{franchiseId}/master-menus/{menuId}/categories/{categoryId}', function (Request $request, int $franchiseId, int $menuId, int $categoryId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->destroyCategory($request, $franchiseId, $menuId, $categoryId);
});

// Master Menu Items
Route::post('/franchises/{franchiseId}/master-menus/{menuId}/categories/{categoryId}/items', function (Request $request, int $franchiseId, int $menuId, int $categoryId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->storeItem($request, $franchiseId, $menuId, $categoryId);
});

Route::put('/franchises/{franchiseId}/master-menus/{menuId}/items/{itemId}', function (Request $request, int $franchiseId, int $menuId, int $itemId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->updateItem($request, $franchiseId, $menuId, $itemId);
});

Route::delete('/franchises/{franchiseId}/master-menus/{menuId}/items/{itemId}', function (Request $request, int $franchiseId, int $menuId, int $itemId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->destroyItem($request, $franchiseId, $menuId, $itemId);
});

// Master Menu Sync
Route::post('/franchises/{franchiseId}/master-menus/{menuId}/sync', function (Request $request, int $franchiseId, int $menuId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->syncToBranches($request, $franchiseId, $menuId);
});

Route::post('/franchises/{franchiseId}/master-menus/{menuId}/sync/{branchId}', function (Request $request, int $franchiseId, int $menuId, int $branchId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->syncToSingleBranch($request, $franchiseId, $menuId, $branchId);
});

Route::get('/franchises/{franchiseId}/master-menus/{menuId}/sync-status', function (Request $request, int $franchiseId, int $menuId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->getSyncStatus($request, $franchiseId, $menuId);
});

// Branch Overrides
Route::post('/franchises/{franchiseId}/master-menus/{menuId}/items/{itemId}/override/{branchId}', function (Request $request, int $franchiseId, int $menuId, int $itemId, int $branchId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->setBranchOverride($request, $franchiseId, $menuId, $itemId, $branchId);
});

Route::delete('/franchises/{franchiseId}/master-menus/{menuId}/items/{itemId}/override/{branchId}', function (Request $request, int $franchiseId, int $menuId, int $itemId, int $branchId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuController::class)->removeBranchOverride($request, $franchiseId, $menuId, $itemId, $branchId);
});

/*
|--------------------------------------------------------------------------
| Master Menu Offers Routes
|--------------------------------------------------------------------------
| Routes for managing special offers, instant offers, and seasonal promotions.
*/

// Offers CRUD
Route::get('/franchises/{franchiseId}/master-menus/{menuId}/offers', function (Request $request, int $franchiseId, int $menuId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuOfferController::class)->index($request, $franchiseId, $menuId);
});

Route::post('/franchises/{franchiseId}/master-menus/{menuId}/offers', function (Request $request, int $franchiseId, int $menuId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuOfferController::class)->store($request, $franchiseId, $menuId);
});

Route::get('/franchises/{franchiseId}/master-menus/{menuId}/offers/{offerId}', function (Request $request, int $franchiseId, int $menuId, int $offerId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuOfferController::class)->show($request, $franchiseId, $menuId, $offerId);
});

Route::put('/franchises/{franchiseId}/master-menus/{menuId}/offers/{offerId}', function (Request $request, int $franchiseId, int $menuId, int $offerId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuOfferController::class)->update($request, $franchiseId, $menuId, $offerId);
});

Route::delete('/franchises/{franchiseId}/master-menus/{menuId}/offers/{offerId}', function (Request $request, int $franchiseId, int $menuId, int $offerId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuOfferController::class)->destroy($request, $franchiseId, $menuId, $offerId);
});

// Offer Actions
Route::post('/franchises/{franchiseId}/master-menus/{menuId}/offers/{offerId}/toggle', function (Request $request, int $franchiseId, int $menuId, int $offerId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuOfferController::class)->toggle($request, $franchiseId, $menuId, $offerId);
});

Route::post('/franchises/{franchiseId}/master-menus/{menuId}/offers/{offerId}/duplicate', function (Request $request, int $franchiseId, int $menuId, int $offerId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuOfferController::class)->duplicate($request, $franchiseId, $menuId, $offerId);
});

// Branch Offer Overrides
Route::post('/franchises/{franchiseId}/master-menus/{menuId}/offers/{offerId}/override/{branchId}', function (Request $request, int $franchiseId, int $menuId, int $offerId, int $branchId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuOfferController::class)->setBranchOverride($request, $franchiseId, $menuId, $offerId, $branchId);
});

Route::delete('/franchises/{franchiseId}/master-menus/{menuId}/offers/{offerId}/override/{branchId}', function (Request $request, int $franchiseId, int $menuId, int $offerId, int $branchId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuOfferController::class)->removeBranchOverride($request, $franchiseId, $menuId, $offerId, $branchId);
});

// Active Offers Query
Route::get('/franchises/{franchiseId}/offers/active', function (Request $request, int $franchiseId) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    return app(MasterMenuOfferController::class)->getActiveOffers($request, $franchiseId);
});

/*
|--------------------------------------------------------------------------
| Universal Template Menu System Routes
|--------------------------------------------------------------------------
| Routes for the universal menu template system that works for:
| - Single restaurants with tables/rooms
| - Multi-location restaurants
| - Franchises
*/

// ===========================================
// PUBLIC MENU ROUTES (No Auth Required)
// ===========================================

// Get menu by short code (customer scans QR)
Route::get('/menu/{shortCode}', [PublicMenuController::class, 'getMenu']);
Route::get('/menu/{shortCode}/data', [PublicMenuController::class, 'getMenuOnly']);
Route::get('/menu/{shortCode}/offers', [PublicMenuController::class, 'getOffers']);
Route::get('/menu/{shortCode}/info', [PublicMenuController::class, 'getEndpointInfo']);
Route::post('/menu/{shortCode}/scan', [PublicMenuController::class, 'recordScan']);

// ===========================================
// AUTHENTICATED ROUTES
// ===========================================

// Menu Templates
Route::get('/menu-templates', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->index($request);
});

Route::post('/menu-templates', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->store($request);
});

Route::get('/menu-templates/{templateId}', function (Request $request, int $templateId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->show($request, $templateId);
});

Route::put('/menu-templates/{templateId}', function (Request $request, int $templateId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->update($request, $templateId);
});

Route::delete('/menu-templates/{templateId}', function (Request $request, int $templateId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->destroy($request, $templateId);
});

Route::post('/menu-templates/{templateId}/duplicate', function (Request $request, int $templateId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->duplicate($request, $templateId);
});

// Template Categories
Route::post('/menu-templates/{templateId}/categories', function (Request $request, int $templateId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->addCategory($request, $templateId);
});

Route::put('/menu-templates/{templateId}/categories/{categoryId}', function (Request $request, int $templateId, int $categoryId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->updateCategory($request, $templateId, $categoryId);
});

Route::delete('/menu-templates/{templateId}/categories/{categoryId}', function (Request $request, int $templateId, int $categoryId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->deleteCategory($request, $templateId, $categoryId);
});

Route::post('/menu-templates/{templateId}/categories/reorder', function (Request $request, int $templateId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->reorderCategories($request, $templateId);
});

// Template Items
Route::post('/menu-templates/{templateId}/categories/{categoryId}/items', function (Request $request, int $templateId, int $categoryId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->addItem($request, $templateId, $categoryId);
});

Route::put('/menu-templates/{templateId}/items/{itemId}', function (Request $request, int $templateId, int $itemId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->updateItem($request, $templateId, $itemId);
});

Route::delete('/menu-templates/{templateId}/items/{itemId}', function (Request $request, int $templateId, int $itemId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->deleteItem($request, $templateId, $itemId);
});

Route::post('/menu-templates/{templateId}/items/bulk', function (Request $request, int $templateId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuTemplateController::class)->bulkUpdateItems($request, $templateId);
});

// ===========================================
// MENU ENDPOINTS (Tables, Rooms, etc.)
// ===========================================

Route::get('/menu-endpoints', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->index($request);
});

Route::post('/menu-endpoints', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->store($request);
});

Route::post('/menu-endpoints/bulk', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->bulkCreate($request);
});

Route::get('/menu-endpoints/{endpointId}', function (Request $request, int $endpointId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->show($request, $endpointId);
});

Route::put('/menu-endpoints/{endpointId}', function (Request $request, int $endpointId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->update($request, $endpointId);
});

Route::delete('/menu-endpoints/{endpointId}', function (Request $request, int $endpointId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->destroy($request, $endpointId);
});

Route::post('/menu-endpoints/{endpointId}/regenerate-qr', function (Request $request, int $endpointId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->regenerateQr($request, $endpointId);
});

Route::get('/menu-endpoints/{endpointId}/qr', function (Request $request, int $endpointId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->getQrCode($request, $endpointId);
});

Route::get('/menu-endpoints/{endpointId}/analytics', function (Request $request, int $endpointId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->analytics($request, $endpointId);
});

// Endpoint Overrides
Route::get('/menu-endpoints/{endpointId}/overrides', function (Request $request, int $endpointId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->getOverrides($request, $endpointId);
});

Route::post('/menu-endpoints/{endpointId}/overrides', function (Request $request, int $endpointId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->setOverride($request, $endpointId);
});

Route::delete('/menu-endpoints/{endpointId}/overrides/{itemId}', function (Request $request, int $endpointId, int $itemId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->removeOverride($request, $endpointId, $itemId);
});

Route::post('/menu-endpoints/{endpointId}/overrides/bulk', function (Request $request, int $endpointId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuEndpointController::class)->bulkSetOverrides($request, $endpointId);
});

// ===========================================
// MENU OFFERS
// ===========================================

Route::get('/menu-offers', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuOfferController::class)->index($request);
});

Route::post('/menu-offers', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuOfferController::class)->store($request);
});

Route::get('/menu-offers/{offerId}', function (Request $request, int $offerId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuOfferController::class)->show($request, $offerId);
});

Route::put('/menu-offers/{offerId}', function (Request $request, int $offerId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuOfferController::class)->update($request, $offerId);
});

Route::delete('/menu-offers/{offerId}', function (Request $request, int $offerId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuOfferController::class)->destroy($request, $offerId);
});

Route::post('/menu-offers/{offerId}/toggle', function (Request $request, int $offerId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuOfferController::class)->toggleActive($request, $offerId);
});

Route::post('/menu-offers/{offerId}/duplicate', function (Request $request, int $offerId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuOfferController::class)->duplicate($request, $offerId);
});

Route::get('/menu-offers/type/{type}', function (Request $request, string $type) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuOfferController::class)->byType($request, $type);
});