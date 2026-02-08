<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FranchiseContextController;
use App\Http\Controllers\HelpTicketController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\MenuCategoryController;
use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPaymentController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminActivityController;
use App\Http\Controllers\Admin\AdminFranchiseController;
use App\Http\Controllers\Admin\AdminFranchiseOnboardingController;
use App\Http\Controllers\Admin\AdminFranchiseEndpointController;
use App\Http\Controllers\Admin\AdminFranchiseMenuController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\MasterMenuController;
use App\Http\Controllers\MasterMenuOfferController;
use App\Http\Controllers\MenuTemplateController;
use App\Http\Controllers\MenuEndpointController;
use App\Http\Controllers\MenuOfferController;
use App\Http\Controllers\PublicMenuController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;


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

// Custom Broadcasting auth route (handles Sanctum token manually)
Route::post('/broadcasting/auth', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'Token required'], 401);
    }
    
    $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$personalAccessToken) {
        return response()->json(['error' => 'Invalid token'], 403);
    }
    
    $user = $personalAccessToken->tokenable;
    $request->setUserResolver(fn() => $user);
    
    // Use Laravel's broadcast auth
    return Broadcast::auth($request);
});

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

// Payment Gateway Status Check
Route::get('/payment-gateway-status', function () {
    try {
        $serviceExists = class_exists(\App\Services\AbstercoPaymentService::class);
        $controllerExists = class_exists(\App\Http\Controllers\SubscriptionPaymentController::class);
        
        return response()->json([
            'status' => 'ok',
            'service_class_exists' => $serviceExists,
            'controller_class_exists' => $controllerExists,
            'configuration' => [
                'api_key_set' => !empty(config('services.absterco.api_key')),
                'base_url' => config('services.absterco.base_url'),
                'organization_id' => config('services.absterco.organization_id'),
            ],
            'git_commit' => trim(shell_exec('git rev-parse --short HEAD 2>&1') ?? 'unknown'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});

// Test payment initiation (for debugging)
Route::get('/test-payment-init', function () {
    try {
        $user = \App\Models\User::first();
        $plan = \App\Models\SubscriptionPlan::where('price', '>', 0)->first();
        
        if (!$user || !$plan) {
            return response()->json(['error' => 'No user or plan found']);
        }
        
        $service = new \App\Services\AbstercoPaymentService();
        $amount = $service->calculateSubscriptionAmount($plan, true);
        $reference = $service->generatePaymentReference($user->id, $plan->id);
        
        $paymentData = $service->createSubscriptionPayment([
            'amount' => $amount,
            'currency' => 'LKR',
            'description' => "Test: {$plan->name}",
            'order_reference' => $reference,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'external_customer_id' => (string) $user->id,
            'allow_save_card' => true,
            'return_url' => config('app.frontend_url') . '/dashboard/subscription/payment-callback',
            'subscription_plan_id' => $plan->id,
            'user_id' => $user->id,
            'payment_type' => 'test',
        ]);
        
        return response()->json([
            'success' => true,
            'user' => $user->name,
            'plan' => $plan->name,
            'amount' => $amount,
            'payment_url' => $paymentData['payment_url'],
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
        ], 500);
    }
});

// Debug mail configuration (TEMPORARY - remove after debugging)
Route::get('/debug-mail', function () {
    $config = [
        'mail_driver' => config('mail.default'),
        'mail_host' => config('mail.mailers.smtp.host'),
        'mail_port' => config('mail.mailers.smtp.port'),
        'mail_encryption' => config('mail.mailers.smtp.encryption'),
        'mail_username' => config('mail.mailers.smtp.username'),
        'mail_password_set' => !empty(config('mail.mailers.smtp.password')) ? 'YES' : 'NO',
        'mail_from_address' => config('mail.from.address'),
        'mail_from_name' => config('mail.from.name'),
        'openssl_loaded' => extension_loaded('openssl') ? 'YES' : 'NO',
        'openssl_version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'N/A',
        'php_version' => PHP_VERSION,
        'loaded_extensions' => implode(', ', get_loaded_extensions()),
    ];
    
    \Log::info('Mail debug info', $config);
    
    return response()->json([
        'success' => true,
        'mail_config' => $config,
    ]);
});

// Google OAuth routes
Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);
Route::post('/auth/google', [SocialAuthController::class, 'googleAuth']);

// Public logo serving route
Route::get('/logos/{filename}', function ($filename) {
    $path = storage_path('app/public/logos/' . $filename);
    
    if (!file_exists($path)) {
        abort(404, 'Logo not found');
    }
    
    $mimeType = mime_content_type($path);
    
    return response()->file($path, [
        'Content-Type' => $mimeType,
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('filename', '.*');

// Deployment status check (no auth required)
Route::get('/deployment-status', function () {
    return response()->json([
        'status' => 'ok',
        'git_commit' => exec('git rev-parse --short HEAD 2>&1'),
        'timestamp' => now()->toISOString(),
        'service_exists' => class_exists('App\\Services\\AbstercoPaymentService'),
        'controller_exists' => class_exists('App\\Http\\Controllers\\SubscriptionPaymentController'),
        'order_ref_method' => method_exists('App\\Services\\AbstercoPaymentService', 'generatePaymentReference'),
    ]);
});

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
Route::post('/menus/{id}/sync', [MenuController::class, 'syncToLocations']); // Menu sync endpoint
Route::get('/menus/{menu}/limits', [MenuItemController::class, 'checkLimits']);
Route::put('/menus/{menu}/items/reorder', [MenuItemController::class, 'reorder']);
Route::apiResource('menus.items', MenuItemController::class);
Route::get('/menus/{menu}/categories', [MenuCategoryController::class, 'index']);
Route::post('/menus/{menu}/categories', [MenuCategoryController::class, 'store']);
Route::put('/menus/{menu}/categories/{category}', [MenuCategoryController::class, 'update']);
Route::delete('/menus/{menu}/categories/{category}', [MenuCategoryController::class, 'destroy']);
Route::post('/menus/{menu}/categories/reorder', [MenuCategoryController::class, 'reorder']);

// Menu version control routes
Route::get('/menus/{menuId}/versions', [\App\Http\Controllers\Api\MenuVersionController::class, 'index']);
Route::post('/menus/{menuId}/versions', [\App\Http\Controllers\Api\MenuVersionController::class, 'store']);
Route::get('/menus/{menuId}/versions/{versionNumber}', [\App\Http\Controllers\Api\MenuVersionController::class, 'show']);
Route::post('/menus/{menuId}/versions/{versionNumber}/restore', [\App\Http\Controllers\Api\MenuVersionController::class, 'restore']);
Route::delete('/menus/{menuId}/versions/{versionNumber}', [\App\Http\Controllers\Api\MenuVersionController::class, 'destroy']);

// Franchise configuration routes
Route::get('/franchise/features', [\App\Http\Controllers\Api\FranchiseConfigController::class, 'features']);
Route::get('/franchise/config', [\App\Http\Controllers\Api\FranchiseConfigController::class, 'config']);
Route::get('/franchise/custom-fields', [\App\Http\Controllers\Api\FranchiseConfigController::class, 'customFields']);
Route::get('/franchise/features/{feature}', [\App\Http\Controllers\Api\FranchiseConfigController::class, 'hasFeature']);

// Location management routes (manual auth)
Route::apiResource('locations', LocationController::class);
Route::post('/locations/{location}/set-default', [LocationController::class, 'setDefault']);
Route::put('/locations/sort-order', [LocationController::class, 'updateSortOrder']);
Route::get('/locations/{location}/statistics', [LocationController::class, 'statistics']);

// QR Code routes (manual auth)
Route::get('/qr-codes', [QRCodeController::class, 'index']);
Route::post('/qr-codes', [QRCodeController::class, 'store']);
Route::delete('/qr-codes/{id}', [QRCodeController::class, 'destroy']);

// Dynamic QR Code Generation (authenticated)
Route::get('/qr/{code}/preview', [QRCodeController::class, 'preview']);
Route::post('/qr/bulk-preview', [QRCodeController::class, 'bulkPreview']);

// Public Dynamic QR Code routes (no auth - generated on-demand, no storage)
Route::get('/qr/{code}', [QRCodeController::class, 'generateDynamic'])->name('qr.generate');
Route::get('/qr/{code}/download', [QRCodeController::class, 'downloadDynamic'])->name('qr.download');

// Location-aware menu routes
Route::apiResource('locations.menus', MenuController::class);
Route::apiResource('locations.menus.items', MenuItemController::class);

// Subscription routes (manual auth)
Route::get('/subscription-plans', [SubscriptionController::class, 'getPlans']);
Route::get('/subscription/current', [SubscriptionController::class, 'getCurrentSubscription']);
Route::post('/subscription/trial/{planId}', [SubscriptionController::class, 'startTrial']);
Route::get('/subscription/recommendations', [SubscriptionController::class, 'getUpgradeRecommendations']);

// Subscription Payment routes (auth required)
Route::post('/subscriptions/upgrade', [SubscriptionPaymentController::class, 'initiateUpgrade']);
Route::post('/subscriptions/change', [SubscriptionPaymentController::class, 'initiateUpgrade']); // Alias for frontend compatibility
Route::get('/subscriptions/payment-callback', [SubscriptionPaymentController::class, 'paymentCallback']);
Route::get('/subscriptions/saved-cards', [SubscriptionPaymentController::class, 'getSavedCards']);
Route::post('/subscriptions/saved-cards/{cardId}/default', [SubscriptionPaymentController::class, 'setDefaultCard']);
Route::delete('/subscriptions/saved-cards/{cardId}', [SubscriptionPaymentController::class, 'deleteSavedCard']);

// Help Ticket routes (user tickets - manual auth)
Route::get('/help-tickets/options', [HelpTicketController::class, 'options']);
Route::get('/help-tickets', [HelpTicketController::class, 'index']);
Route::post('/help-tickets', [HelpTicketController::class, 'store']);
Route::get('/help-tickets/{id}', [HelpTicketController::class, 'show']);
Route::post('/help-tickets/{id}/messages', [HelpTicketController::class, 'addMessage']);
Route::post('/help-tickets/{id}/status', [HelpTicketController::class, 'updateStatus']);

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
        Route::post('/branches', [FranchiseContextController::class, 'createBranch']);
        Route::put('/branches/{branchId}', [FranchiseContextController::class, 'updateBranch']);
        Route::delete('/branches/{branchId}', [FranchiseContextController::class, 'deleteBranch']);
        
        // Locations
        Route::get('/locations', [FranchiseContextController::class, 'locations']);
        
        // Menus
        Route::get('/menus', [FranchiseContextController::class, 'menus']);
        Route::get('/menus/{menuId}', [FranchiseContextController::class, 'getMenu']);
        Route::post('/menus/{menuId}/bulk-update', [FranchiseContextController::class, 'bulkUpdateMenuItems']);
        
        // Menu Endpoints (Tables, QR Codes, etc.)
        Route::get('/endpoints', [FranchiseContextController::class, 'endpoints']);
        Route::post('/endpoints', [FranchiseContextController::class, 'createEndpoint']);
        Route::post('/endpoints/bulk', [FranchiseContextController::class, 'bulkCreateEndpoints']);
        Route::get('/endpoints/{endpointId}', [FranchiseContextController::class, 'getEndpoint']);
        Route::put('/endpoints/{endpointId}', [FranchiseContextController::class, 'updateEndpoint']);
        Route::delete('/endpoints/{endpointId}', [FranchiseContextController::class, 'deleteEndpoint']);
        Route::get('/endpoints/{endpointId}/qr', [FranchiseContextController::class, 'getEndpointQR']);
        Route::post('/endpoints/{endpointId}/qr/regenerate', [FranchiseContextController::class, 'regenerateEndpointQR']);
        
        // Staff/Team
        Route::get('/staff', [FranchiseContextController::class, 'staff']);
        Route::post('/staff', [FranchiseContextController::class, 'inviteStaff']);
        Route::put('/staff/{staffId}', [FranchiseContextController::class, 'updateStaff']);
        Route::delete('/staff/{staffId}', [FranchiseContextController::class, 'removeStaff']);
        
        // Settings
        Route::get('/settings', [FranchiseContextController::class, 'settings']);
        Route::put('/settings', [FranchiseContextController::class, 'updateSettings']);
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
    Route::post('/users/{id}/send-password', [AdminUserController::class, 'generateAndSendPassword']);
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
    Route::get('/tickets/available-staff', [AdminSupportController::class, 'getAvailableStaff']);
    Route::get('/tickets/{id}', [AdminSupportController::class, 'show']);
    Route::post('/tickets/{id}/assign', [AdminSupportController::class, 'assign']);
    Route::post('/tickets/{id}/auto-assign', [AdminSupportController::class, 'autoAssign']);
    Route::post('/tickets/{id}/self-assign', [AdminSupportController::class, 'selfAssign']);
    Route::post('/tickets/{id}/status', [AdminSupportController::class, 'updateStatus']);
    Route::post('/tickets/{id}/priority', [AdminSupportController::class, 'updatePriority']);
    Route::post('/tickets/{id}/messages', [AdminSupportController::class, 'addMessage']);
    
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications', [NotificationController::class, 'clearAll']);
    Route::post('/status/heartbeat', [NotificationController::class, 'heartbeat']);
    Route::post('/status/offline', [NotificationController::class, 'goOffline']);
    
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
    
    // Franchise QR Code/Endpoint Management
    Route::get('/franchises/{franchiseId}/endpoints', [AdminFranchiseEndpointController::class, 'index']);
    Route::get('/franchises/{franchiseId}/endpoints/statistics', [AdminFranchiseEndpointController::class, 'statistics']);
    Route::get('/franchises/{franchiseId}/endpoints/{endpointId}', [AdminFranchiseEndpointController::class, 'show']);
    Route::post('/franchises/{franchiseId}/endpoints', [AdminFranchiseEndpointController::class, 'store']);
    Route::post('/franchises/{franchiseId}/endpoints/bulk', [AdminFranchiseEndpointController::class, 'bulkCreate']);
    Route::put('/franchises/{franchiseId}/endpoints/{endpointId}', [AdminFranchiseEndpointController::class, 'update']);
    Route::delete('/franchises/{franchiseId}/endpoints/{endpointId}', [AdminFranchiseEndpointController::class, 'destroy']);
    
    // Franchise Menu Template Management
    Route::get('/franchises/{franchiseId}/menus', [AdminFranchiseMenuController::class, 'indexTemplates']);
    Route::get('/franchises/{franchiseId}/menus/statistics', [AdminFranchiseMenuController::class, 'statistics']);
    Route::get('/franchises/{franchiseId}/menus/{templateId}', [AdminFranchiseMenuController::class, 'showTemplate']);
    Route::post('/franchises/{franchiseId}/menus', [AdminFranchiseMenuController::class, 'storeTemplate']);
    Route::put('/franchises/{franchiseId}/menus/{templateId}', [AdminFranchiseMenuController::class, 'updateTemplate']);
    Route::delete('/franchises/{franchiseId}/menus/{templateId}', [AdminFranchiseMenuController::class, 'destroyTemplate']);
    
    // Franchise Menu Categories
    Route::get('/franchises/{franchiseId}/menus/{templateId}/categories', [AdminFranchiseMenuController::class, 'indexCategories']);
    Route::post('/franchises/{franchiseId}/menus/{templateId}/categories', [AdminFranchiseMenuController::class, 'storeCategory']);
    Route::put('/franchises/{franchiseId}/menus/{templateId}/categories/{categoryId}', [AdminFranchiseMenuController::class, 'updateCategory']);
    Route::delete('/franchises/{franchiseId}/menus/{templateId}/categories/{categoryId}', [AdminFranchiseMenuController::class, 'destroyCategory']);
    
    // Franchise Menu Items
    Route::get('/franchises/{franchiseId}/menus/{templateId}/items', [AdminFranchiseMenuController::class, 'indexItems']);
    Route::post('/franchises/{franchiseId}/menus/{templateId}/items', [AdminFranchiseMenuController::class, 'storeItem']);
    Route::put('/franchises/{franchiseId}/menus/{templateId}/items/{itemId}', [AdminFranchiseMenuController::class, 'updateItem']);
    Route::delete('/franchises/{franchiseId}/menus/{templateId}/items/{itemId}', [AdminFranchiseMenuController::class, 'destroyItem']);
    Route::post('/franchises/{franchiseId}/menus/{templateId}/items/bulk-availability', [AdminFranchiseMenuController::class, 'bulkUpdateAvailability']);
});

// Protected routes (with sanctum middleware for cookie-based auth)
Route::middleware('auth:sanctum')->group(function () {
    // Dashboard stats
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    
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
    
    // Customer-specific routes (authenticated customers)
    Route::prefix('customer')->group(function () {
        Route::get('/profile', [App\Http\Controllers\CustomerAuthController::class, 'profile']);
        Route::post('/logout', [App\Http\Controllers\CustomerAuthController::class, 'logout']);
    });
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'service' => 'MenuVire API'
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

// Public menu by endpoint code (for QR codes)
// Supports both franchise and regular business menus
Route::get('/public/menu/endpoint/{code}', function (string $code) {
    // First try to find a franchise endpoint
    $franchiseEndpoint = \App\Models\MenuEndpoint::with(['location', 'franchise', 'template'])
        ->where('short_code', $code)
        ->whereNotNull('franchise_id')
        ->first();
    
    if ($franchiseEndpoint) {
        return app(\App\Http\Controllers\Api\FranchiseController::class)->getMenuByEndpointCode(request(), $code);
    }
    
    // Otherwise, use PublicMenuController for regular business menus
    return app(\App\Http\Controllers\PublicMenuController::class)->getMenu(request(), $code);
});

// QR Scan Session Routes (public - no auth required)
Route::prefix('public/sessions')->group(function () {
    Route::post('/{shortCode}', [App\Http\Controllers\QrSessionController::class, 'createOrUpdate']);
    Route::post('/link-loyalty', [App\Http\Controllers\QrSessionController::class, 'linkLoyalty']);
    Route::get('/{sessionToken}', [App\Http\Controllers\QrSessionController::class, 'show']);
    Route::get('/{sessionToken}/orders', [App\Http\Controllers\QrSessionController::class, 'getOrders']);
    Route::get('/{sessionToken}/history', [App\Http\Controllers\QrSessionController::class, 'getHistory']);
});

// Customer Authentication Routes (public - no auth required)
Route::prefix('public/auth')->group(function () {
    Route::post('/send-otp', [App\Http\Controllers\CustomerAuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [App\Http\Controllers\CustomerAuthController::class, 'verifyOtp']);
    Route::post('/register', [App\Http\Controllers\CustomerAuthController::class, 'register']);
});

// DEMO: Barista Loyalty OTP Authentication (public routes)
Route::prefix('{franchise}/loyalty')->group(function () {
    Route::post('send-otp', [App\Http\Controllers\LoyaltyOtpController::class, 'sendOtp']);
    Route::post('verify-otp', [App\Http\Controllers\LoyaltyOtpController::class, 'verifyOtp']);
    Route::post('register', [App\Http\Controllers\LoyaltyOtpController::class, 'registerLoyalty']);
    Route::post('save-card', [App\Http\Controllers\LoyaltyOtpController::class, 'saveCard']);
});

// Franchise invitation validation and acceptance (public routes)
Route::post('/franchise-invitations/validate', [App\Http\Controllers\Api\FranchiseInvitationController::class, 'validateInvitation']);
Route::post('/franchise-invitations/accept', [App\Http\Controllers\Api\FranchiseInvitationController::class, 'accept']);

// Legacy route for FranchiseUser-based invitations
Route::post('/franchise-user-invitations/accept', [FranchiseUserController::class, 'acceptInvitation']);

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
Route::get('/menu/{shortCode}/config', [PublicMenuController::class, 'getConfig']); // API-driven config
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

// ============================================
// MENU VERSION CONTROL & SYNC ROUTES
// ============================================

use App\Http\Controllers\MenuSyncController;

// Sync status for a branch
Route::get('/menu-sync/status/{locationId}/{masterMenuId}', function (Request $request, int $locationId, int $masterMenuId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->getStatus($request, $locationId, $masterMenuId);
});

// Get pending changes preview
Route::get('/menu-sync/{branchSyncId}/pending', function (Request $request, int $branchSyncId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->getPendingChanges($request, $branchSyncId);
});

// Manual sync trigger
Route::post('/menu-sync/{branchSyncId}/sync', function (Request $request, int $branchSyncId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->syncBranch($request, $branchSyncId);
});

// Update sync mode
Route::put('/menu-sync/{branchSyncId}/mode', function (Request $request, int $branchSyncId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->updateSyncMode($request, $branchSyncId);
});

// Set item override
Route::post('/menu-sync/{branchSyncId}/override/{masterItemId}', function (Request $request, int $branchSyncId, int $masterItemId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->setItemOverride($request, $branchSyncId, $masterItemId);
});

// Remove item override
Route::delete('/menu-sync/{branchSyncId}/override/{masterItemId}', function (Request $request, int $branchSyncId, int $masterItemId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->removeItemOverride($branchSyncId, $masterItemId);
});

// Get all overrides for a branch
Route::get('/menu-sync/{branchSyncId}/overrides', function (Request $request, int $branchSyncId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->getOverrides($branchSyncId);
});

// Get sync history/logs
Route::get('/menu-sync/{branchSyncId}/history', function (Request $request, int $branchSyncId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->getSyncHistory($branchSyncId);
});

// Get version history for a master menu
Route::get('/menu-sync/versions/{masterMenuId}', function (Request $request, int $masterMenuId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->getVersionHistory($masterMenuId);
});

// Initialize branch sync link
Route::post('/menu-sync/initialize', function (Request $request) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->initializeBranchSync($request);
});

// Franchise sync dashboard
Route::get('/menu-sync/dashboard/{franchiseId}', function (Request $request, int $franchiseId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->getSyncDashboard($request, $franchiseId);
});

// Get specific version snapshot (view old menu)
Route::get('/menu-sync/versions/{masterMenuId}/snapshot/{versionNumber}', function (Request $request, int $masterMenuId, int $versionNumber) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->getVersionSnapshot($masterMenuId, $versionNumber);
});

// Compare two versions
Route::get('/menu-sync/versions/{masterMenuId}/compare/{fromVersion}/{toVersion}', function (Request $request, int $masterMenuId, int $fromVersion, int $toVersion) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->compareVersions($masterMenuId, $fromVersion, $toVersion);
});

// Bulk sync all branches
Route::post('/menu-sync/bulk/{masterMenuId}', function (Request $request, int $masterMenuId) {
    $token = $request->bearerToken();
    if (!$token) return response()->json(['error' => 'Token required'], 401);
    $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
    if (!$pat) return response()->json(['error' => 'Invalid token'], 401);
    $request->setUserResolver(fn() => $pat->tokenable);
    return app(MenuSyncController::class)->bulkSyncAllBranches($request, $masterMenuId);
});

}); // End of Route::middleware('auth:sanctum')->group