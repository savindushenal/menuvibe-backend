<?php

namespace App\Http\Controllers;

use App\Models\QRCode;
use App\Models\Location;
use App\Models\MenuEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrCodeGenerator;

class QRCodeController extends Controller
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
     * Get all QR codes for a location
     */
    public function index(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $locationId = $request->query('location_id');
        
        if (!$locationId) {
            return response()->json([
                'success' => false,
                'message' => 'Location ID is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify user owns this location
        $location = Location::where('id', $locationId)
            ->where('user_id', $user->id)
            ->first();

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $qrCodes = QRCode::where('location_id', $locationId)
            ->with('menu:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'qr_codes' => $qrCodes
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Create a new QR code
     */
    public function store(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validator = Validator::make($request->all(), [
            'location_id' => 'required|exists:locations,id',
            'menu_id' => 'nullable|exists:menus,id',
            'name' => 'required|string|max:255',
            'table_number' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Verify user owns this location
        $location = Location::where('id', $request->location_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Generate QR URL (pointing to menu)
        $baseUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $qrUrl = $request->menu_id 
            ? "{$baseUrl}/menu/{$request->menu_id}"
            : "{$baseUrl}/location/{$location->id}";
        
        if ($request->table_number) {
            $qrUrl .= "?table={$request->table_number}";
        }

        // For now, store a placeholder for QR image
        // In production, you'd generate actual QR code image here
        $qrImage = "data:image/svg+xml;base64," . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="white"/><text x="100" y="100" text-anchor="middle" fill="black">QR Code</text></svg>'
        );

        $qrCode = QRCode::create([
            'location_id' => $request->location_id,
            'menu_id' => $request->menu_id,
            'name' => $request->name,
            'table_number' => $request->table_number,
            'qr_url' => $qrUrl,
            'qr_image' => $qrImage,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR code created successfully',
            'data' => $qrCode->load('menu:id,name')
        ], Response::HTTP_CREATED);
    }

    /**
     * Delete a QR code
     */
    public function destroy(Request $request, $id)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $qrCode = QRCode::whereHas('location', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->find($id);

        if (!$qrCode) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $qrCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'QR code deleted successfully'
        ], Response::HTTP_OK);
    }
    
    /**
     * Generate QR code dynamically as SVG (no storage)
     * Public endpoint - no authentication required
     * 
     * @param string $code
     * @return \Illuminate\Http\Response
     */
    public function generateDynamic($code)
    {
        $endpoint = MenuEndpoint::where('identifier', $code)
            ->with(['location.user.businessProfile'])
            ->first();
        
        if (!$endpoint) {
            // Return a placeholder QR with error message
            $errorSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300"><rect width="300" height="300" fill="#f8f9fa"/><text x="150" y="150" text-anchor="middle" fill="#dc3545" font-size="20">Invalid QR Code</text></svg>';
            return response($errorSvg, 404)
                ->header('Content-Type', 'image/svg+xml');
        }
        
        // Get business branding colors
        $business = $endpoint->location?->user->businessProfile;
        $primaryColor = $business?->primary_color ?? '#000000';
        
        // Generate public menu URL
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $url = $frontendUrl . '/m/' . $code;
        
        // Convert hex color to RGB
        $r = hexdec(substr($primaryColor, 1, 2));
        $g = hexdec(substr($primaryColor, 3, 2));
        $b = hexdec(substr($primaryColor, 5, 2));
        
        // Generate QR code as SVG with branding
        $qr = QrCodeGenerator::size(300)
            ->style('round')
            ->eye('circle')
            ->color($r, $g, $b)
            ->backgroundColor(255, 255, 255)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($url);
        
        return response($qr, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-QR-Endpoint', $endpoint->name)
            ->header('X-QR-Type', $endpoint->type);
    }
    
    /**
     * Download QR code as PNG or JPG (generated on-demand)
     * Public endpoint - no authentication required
     * 
     * @param \Illuminate\Http\Request $request
     * @param string $code
     * @return \Illuminate\Http\Response
     */
    public function downloadDynamic(Request $request, $code)
    {
        $endpoint = MenuEndpoint::where('identifier', $code)
            ->with(['location.user.businessProfile'])
            ->first();
        
        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found'
            ], 404);
        }
        
        // Get parameters
        $format = $request->query('format', 'png');
        $size = (int) $request->query('size', 512);
        
        // Validate format
        if (!in_array($format, ['png', 'jpg', 'jpeg'])) {
            $format = 'png';
        }
        
        // Limit size to prevent abuse (256px - 2048px)
        $size = max(256, min($size, 2048));
        
        // Get business branding
        $business = $endpoint->location?->user->businessProfile;
        $primaryColor = $business?->primary_color ?? '#000000';
        
        // Generate URL
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $url = $frontendUrl . '/m/' . $code;
        
        // Convert hex to RGB
        $r = hexdec(substr($primaryColor, 1, 2));
        $g = hexdec(substr($primaryColor, 3, 2));
        $b = hexdec(substr($primaryColor, 5, 2));
        
        // Generate QR code
        $qr = QrCodeGenerator::format($format)
            ->size($size)
            ->style('round')
            ->eye('circle')
            ->color($r, $g, $b)
            ->backgroundColor(255, 255, 255)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($url);
        
        // Generate safe filename
        $safeName = preg_replace('/[^a-zA-Z0-9-_]/', '-', $endpoint->name);
        $filename = 'qr-' . $safeName . '-' . $code . '.' . $format;
        
        $mimeType = $format === 'png' ? 'image/png' : 'image/jpeg';
        
        return response($qr, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('X-QR-Endpoint', $endpoint->name)
            ->header('X-QR-Size', $size);
    }
    
    /**
     * Get QR code preview with metadata (for dashboard display)
     * Requires authentication
     * 
     * @param \Illuminate\Http\Request $request
     * @param string $code
     * @return \Illuminate\Http\JsonResponse
     */
    public function preview(Request $request, $code)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        $endpoint = MenuEndpoint::where('identifier', $code)
            ->whereHas('location', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['location.user.businessProfile'])
            ->first();
        
        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found'
            ], 404);
        }
        
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $apiUrl = config('app.url', 'http://localhost:8000');
        
        return response()->json([
            'success' => true,
            'data' => [
                'endpoint' => [
                    'id' => $endpoint->id,
                    'name' => $endpoint->name,
                    'type' => $endpoint->type,
                    'identifier' => $endpoint->identifier,
                ],
                'location' => $endpoint->location ? [
                    'id' => $endpoint->location->id,
                    'name' => $endpoint->location->name,
                ] : null,
                'business' => $endpoint->location?->user->businessProfile ? [
                    'name' => $endpoint->location->user->businessProfile->business_name,
                    'primary_color' => $endpoint->location->user->businessProfile->primary_color,
                ] : null,
                'urls' => [
                    'menu_url' => $frontendUrl . '/m/' . $code,
                    'qr_svg' => $apiUrl . '/api/qr/' . $code,
                    'qr_png_512' => $apiUrl . '/api/qr/' . $code . '/download?format=png&size=512',
                    'qr_png_1024' => $apiUrl . '/api/qr/' . $code . '/download?format=png&size=1024',
                    'qr_png_2048' => $apiUrl . '/api/qr/' . $code . '/download?format=png&size=2048',
                    'qr_jpg_1024' => $apiUrl . '/api/qr/' . $code . '/download?format=jpg&size=1024',
                ],
            ]
        ]);
    }
    
    /**
     * Bulk generate QR codes data for multiple endpoints
     * Requires authentication
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkPreview(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        $request->validate([
            'endpoint_ids' => 'required|array',
            'endpoint_ids.*' => 'integer|exists:menu_endpoints,id'
        ]);
        
        $endpoints = MenuEndpoint::whereIn('id', $request->endpoint_ids)
            ->whereHas('location', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['location.user.businessProfile'])
            ->get();
        
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $apiUrl = config('app.url', 'http://localhost:8000');
        
        $qrData = $endpoints->map(function ($endpoint) use ($frontendUrl, $apiUrl) {
            return [
                'endpoint' => [
                    'id' => $endpoint->id,
                    'name' => $endpoint->name,
                    'type' => $endpoint->type,
                    'identifier' => $endpoint->identifier,
                ],
                'location' => $endpoint->location ? [
                    'id' => $endpoint->location->id,
                    'name' => $endpoint->location->name,
                ] : null,
                'urls' => [
                    'menu_url' => $frontendUrl . '/m/' . $endpoint->identifier,
                    'qr_svg' => $apiUrl . '/api/qr/' . $endpoint->identifier,
                    'qr_png_512' => $apiUrl . '/api/qr/' . $endpoint->identifier . '/download?format=png&size=512',
                    'qr_png_1024' => $apiUrl . '/api/qr/' . $endpoint->identifier . '/download?format=png&size=1024',
                ],
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $qrData
        ]);
    }
}
