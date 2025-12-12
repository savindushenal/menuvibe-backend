<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HelpTicketController extends Controller
{
    /**
     * Get user's tickets
     */
    public function index(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $status = $request->get('status', 'all');
        
        $query = SupportTicket::where('user_id', $user->id)
            ->with(['messages' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->orderBy('updated_at', 'desc');

        if ($status !== 'all') {
            if ($status === 'open') {
                $query->open();
            } elseif ($status === 'closed') {
                $query->closed();
            } else {
                $query->where('status', $status);
            }
        }

        $tickets = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    }

    /**
     * Create a new ticket
     */
    public function store(Request $request)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:technical,billing,feature_request,account,franchise,other',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = SupportTicket::create([
            'ticket_number' => SupportTicket::generateTicketNumber(),
            'user_id' => $user->id,
            'subject' => $request->subject,
            'description' => $request->description,
            'category' => $request->category,
            'priority' => $request->priority ?? 'medium',
            'status' => 'open',
        ]);

        // Add initial message (the description)
        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $request->description,
            'is_internal' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'data' => $ticket->load('messages')
        ], 201);
    }

    /**
     * Get a specific ticket
     */
    public function show(Request $request, int $id)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $query = SupportTicket::where('id', $id);
        
        // Admin can view all, users only their own
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            $query->where('user_id', $user->id);
        }

        $ticket = $query->with([
            'messages' => function ($q) use ($user) {
                // Non-staff can't see internal notes
                if (!in_array($user->role, ['admin', 'super_admin'])) {
                    $q->where('is_internal', false);
                }
                $q->with('user:id,name,email,role')->orderBy('created_at', 'asc');
            },
            'user:id,name,email',
            'assignedTo:id,name,email',
        ])->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ticket
        ]);
    }

    /**
     * Add a message to a ticket
     */
    public function addMessage(Request $request, int $id)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $query = SupportTicket::where('id', $id);
        
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            $query->where('user_id', $user->id);
        }

        $ticket = $query->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $message = SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $request->message,
            'is_internal' => false,
        ]);

        // If user replies, update status from waiting_on_customer
        if ($ticket->status === 'waiting_on_customer') {
            $ticket->update(['status' => 'in_progress']);
        }

        $ticket->touch(); // Update updated_at

        return response()->json([
            'success' => true,
            'message' => 'Message added successfully',
            'data' => $message->load('user:id,name,email,role')
        ]);
    }

    /**
     * Update ticket status (user can close/reopen their tickets)
     */
    public function updateStatus(Request $request, int $id)
    {
        $user = $this->getUserFromToken($request);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        $ticket = SupportTicket::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found'
            ], 404);
        }

        $action = $request->action;

        if ($action === 'close') {
            $ticket->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);
            $message = 'Ticket closed successfully';
        } elseif ($action === 'reopen') {
            $ticket->update([
                'status' => 'open',
                'closed_at' => null,
                'resolved_at' => null,
            ]);
            $message = 'Ticket reopened successfully';
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid action'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $ticket
        ]);
    }

    /**
     * Get ticket categories and priorities
     */
    public function options()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => [
                    'technical' => 'Technical Issue',
                    'billing' => 'Billing & Subscription',
                    'feature_request' => 'Feature Request',
                    'account' => 'Account Help',
                    'franchise' => 'Franchise Support',
                    'other' => 'Other',
                ],
                'priorities' => [
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'urgent' => 'Urgent',
                ],
            ]
        ]);
    }

    /**
     * Get user from token (manual auth)
     */
    private function getUserFromToken(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }

        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            return null;
        }

        return $accessToken->tokenable;
    }
}
