<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\MenuCategoryController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Api\SettingsController;
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

// Location-aware menu routes
Route::apiResource('locations.menus', MenuController::class);
Route::apiResource('locations.menus.items', MenuItemController::class);

// Subscription routes (manual auth)
Route::get('/subscription-plans', [SubscriptionController::class, 'getPlans']);
Route::get('/subscription/current', [SubscriptionController::class, 'getCurrentSubscription']);
Route::post('/subscription/trial/{planId}', [SubscriptionController::class, 'startTrial']);
Route::get('/subscription/recommendations', [SubscriptionController::class, 'getUpgradeRecommendations']);

// Protected routes (with sanctum middleware for cookie-based auth)
Route::middleware('auth:sanctum')->group(function () {
    // Route::get('/user', [AuthController::class, 'profile']); // Temporarily disabled due to view config issue
    
    // Additional protected routes can be added here
    Route::get('/dashboard', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Dashboard data',
            'data' => [
                'user' => $request->user(),
                'stats' => [
                    'total_menus' => 0,
                    'total_items' => 0,
                    'total_categories' => 0,
                ]
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