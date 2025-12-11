<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessProfileController;
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