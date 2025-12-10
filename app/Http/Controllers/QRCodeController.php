<?php

namespace App\Http\Controllers;

use App\Models\QRCode;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

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
}
