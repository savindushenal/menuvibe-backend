<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /**
     * Get user settings
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $settings = $user->getSettings();

            return response()->json([
                'success' => true,
                'data' => [
                    'account' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                    ],
                    'notifications' => $settings->getNotificationPreferences(),
                    'security' => $settings->getSecuritySettings(),
                    'privacy' => $settings->getPrivacySettings(),
                    'display' => $settings->getDisplaySettings(),
                    'business' => $settings->getBusinessSettings(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update account settings
     */
    public function updateAccount(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($request->user()->id)
                ],
                'phone' => 'sometimes|nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $user->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Account settings updated successfully',
                'data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update account settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update notification settings
     */
    public function updateNotifications(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email_notifications' => 'sometimes|boolean',
                'push_notifications' => 'sometimes|boolean',
                'sms_notifications' => 'sometimes|boolean',
                'marketing_emails' => 'sometimes|boolean',
                'order_updates' => 'sometimes|boolean',
                'menu_updates' => 'sometimes|boolean',
                'system_alerts' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $settings = $user->getSettings();
            
            $settings->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully',
                'data' => $settings->getNotificationPreferences()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update security settings
     */
    public function updateSecurity(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'two_factor_enabled' => 'sometimes|boolean',
                'session_timeout' => 'sometimes|integer|min:5|max:1440',
                'login_alerts' => 'sometimes|boolean',
                'password_expiry_days' => 'sometimes|nullable|integer|min:30|max:365',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $settings = $user->getSettings();
            
            $settings->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Security settings updated successfully',
                'data' => $settings->getSecuritySettings()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update security settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update privacy settings
     */
    public function updatePrivacy(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'profile_visibility' => 'sometimes|in:public,private,friends',
                'show_online_status' => 'sometimes|boolean',
                'allow_search_engines' => 'sometimes|boolean',
                'data_collection' => 'sometimes|boolean',
                'analytics_tracking' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $settings = $user->getSettings();
            
            $settings->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Privacy settings updated successfully',
                'data' => $settings->getPrivacySettings()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update privacy settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update display settings
     */
    public function updateDisplay(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'theme' => 'sometimes|in:light,dark,auto',
                'language' => 'sometimes|string|max:10',
                'timezone' => 'sometimes|string|max:50',
                'date_format' => 'sometimes|in:DD/MM/YYYY,MM/DD/YYYY,YYYY-MM-DD',
                'time_format' => 'sometimes|in:12,24',
                'items_per_page' => 'sometimes|integer|min:10|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $settings = $user->getSettings();
            
            $settings->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Display settings updated successfully',
                'data' => $settings->getDisplaySettings()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update display settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update business settings
     */
    public function updateBusiness(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'business_hours_display' => 'sometimes|boolean',
                'auto_accept_orders' => 'sometimes|boolean',
                'order_confirmation_required' => 'sometimes|boolean',
                'menu_availability_alerts' => 'sometimes|boolean',
                'customer_feedback_notifications' => 'sometimes|boolean',
                'inventory_alerts' => 'sometimes|boolean',
                'daily_reports' => 'sometimes|boolean',
                'weekly_reports' => 'sometimes|boolean',
                'monthly_reports' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $settings = $user->getSettings();
            
            $settings->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Business settings updated successfully',
                'data' => $settings->getBusinessSettings()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update business settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset settings to default
     */
    public function resetToDefaults(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'sometimes|in:notifications,security,privacy,display,business,all'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $settings = $user->getSettings();
            $category = $request->input('category', 'all');

            if ($category === 'all') {
                $settings->update(UserSettings::getDefaultSettings());
            } else {
                $defaults = UserSettings::getDefaultSettings();
                $categoryDefaults = [];

                switch ($category) {
                    case 'notifications':
                        $notificationFields = [
                            'email_notifications', 'push_notifications', 'sms_notifications',
                            'marketing_emails', 'order_updates', 'menu_updates', 'system_alerts'
                        ];
                        foreach ($notificationFields as $field) {
                            if (isset($defaults[$field])) {
                                $categoryDefaults[$field] = $defaults[$field];
                            }
                        }
                        break;
                    case 'security':
                        $securityFields = [
                            'two_factor_enabled', 'session_timeout', 'login_alerts', 'password_expiry_days'
                        ];
                        foreach ($securityFields as $field) {
                            if (isset($defaults[$field])) {
                                $categoryDefaults[$field] = $defaults[$field];
                            }
                        }
                        break;
                    case 'privacy':
                        $privacyFields = [
                            'profile_visibility', 'show_online_status', 'allow_search_engines',
                            'data_collection', 'analytics_tracking'
                        ];
                        foreach ($privacyFields as $field) {
                            if (isset($defaults[$field])) {
                                $categoryDefaults[$field] = $defaults[$field];
                            }
                        }
                        break;
                    case 'display':
                        $displayFields = [
                            'theme', 'language', 'timezone', 'date_format', 'time_format', 'items_per_page'
                        ];
                        foreach ($displayFields as $field) {
                            if (isset($defaults[$field])) {
                                $categoryDefaults[$field] = $defaults[$field];
                            }
                        }
                        break;
                    case 'business':
                        $businessFields = [
                            'business_hours_display', 'auto_accept_orders', 'order_confirmation_required',
                            'menu_availability_alerts', 'customer_feedback_notifications',
                            'inventory_alerts', 'daily_reports', 'weekly_reports', 'monthly_reports'
                        ];
                        foreach ($businessFields as $field) {
                            if (isset($defaults[$field])) {
                                $categoryDefaults[$field] = $defaults[$field];
                            }
                        }
                        break;
                }

                $settings->update($categoryDefaults);
            }

            return response()->json([
                'success' => true,
                'message' => ucfirst($category) . ' settings reset to defaults successfully',
                'data' => [
                    'account' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                    ],
                    'notifications' => $settings->getNotificationPreferences(),
                    'security' => $settings->getSecuritySettings(),
                    'privacy' => $settings->getPrivacySettings(),
                    'display' => $settings->getDisplaySettings(),
                    'business' => $settings->getBusinessSettings(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
