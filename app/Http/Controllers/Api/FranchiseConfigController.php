<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeatureService;
use Illuminate\Http\Request;

class FranchiseConfigController extends Controller
{
    /**
     * Get available features for current franchise
     */
    public function features(Request $request)
    {
        $features = FeatureService::getAvailableFeatures();
        
        return response()->json([
            'features' => $features,
        ]);
    }

    /**
     * Get franchise configuration
     */
    public function config(Request $request)
    {
        $franchise = $request->user()->franchise;
        
        if (!$franchise) {
            return response()->json([
                'error' => 'No franchise associated with user'
            ], 404);
        }
        
        $config = config("franchise.{$franchise->slug}", config('franchise.default'));
        
        return response()->json([
            'name' => $config['name'],
            'slug' => $franchise->slug,
            'features' => $config['features'],
            'tax_rate' => $config['tax_rate'],
            'currency' => $config['currency'],
            'max_locations' => $config['max_locations'],
            'support_email' => $config['support_email'],
        ]);
    }

    /**
     * Get custom fields definition for franchise
     */
    public function customFields(Request $request)
    {
        $franchise = $request->user()->franchise;
        
        if (!$franchise) {
            return response()->json([
                'error' => 'No franchise associated with user'
            ], 404);
        }
        
        $config = config("franchise.{$franchise->slug}", config('franchise.default'));
        
        return response()->json([
            'custom_fields' => $config['custom_fields'] ?? [],
            'required_fields' => $config['required_menu_fields'] ?? [],
        ]);
    }

    /**
     * Check if franchise has a specific feature
     */
    public function hasFeature(Request $request, string $feature)
    {
        $hasFeature = FeatureService::hasFeature($feature);
        
        return response()->json([
            'feature' => $feature,
            'enabled' => $hasFeature,
        ]);
    }
}
