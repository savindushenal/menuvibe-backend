<?php

namespace App\Http\Controllers\Admin;

use App\Events\TicketUpdated;
use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Notification;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupportController extends Controller
{
    /**
     * List all support tickets
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canHandleSupportTickets()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = SupportTicket::with(['user:id,name,email', 'assignedTo:id,name,email,role']);

        // Status filter
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Priority filter
        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }

        // Category filter
        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        // Assigned to filter
        if ($request->has('assigned_to')) {
            $assignedTo = $request->get('assigned_to');
            if ($assignedTo === 'unassigned') {
                $query->whereNull('assigned_to');
            } elseif ($assignedTo === 'me') {
                $query->where('assigned_to', $admin->id);
            } else {
                $query->where('assigned_to', $assignedTo);
            }
        }

        // Open tickets only
        if ($request->get('open_only')) {
            $query->open();
        }

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->get('per_page', 20), 100);
        $tickets = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Get a specific ticket with messages
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canHandleSupportTickets()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $ticket = SupportTicket::with([
            'user:id,name,email',
            'assignedTo:id,name,email',
            'messages.user:id,name,email,role',
            'views.user:id,name,email,role',
            'assignments' => function ($q) {
                $q->with(['assignee:id,name,email', 'assigner:id,name,email'])
                  ->orderBy('assigned_at', 'desc')
                  ->take(10);
            },
        ])->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        // Record that this admin viewed the ticket
        $ticket->recordView($admin);

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    /**
     * Assign ticket to admin/support officer
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canHandleSupportTickets()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Support officers cannot assign tickets to others
        if ($admin->isSupportOfficer()) {
            return response()->json([
                'success' => false,
                'message' => 'Support officers can only self-assign tickets. Use the "Take Ticket" option instead.',
            ], 403);
        }

        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $validated = $request->validate([
            'admin_id' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $assignee = User::find($validated['admin_id']);
        
        if (!$assignee->canHandleSupportTickets()) {
            return response()->json([
                'success' => false,
                'message' => 'Can only assign to admin or support officer users',
            ], 400);
        }

        $oldAssignee = $ticket->assigned_to;
        
        // Use the new tracking method
        $ticket->assignToWithTracking(
            $assignee, 
            $admin, 
            'manual', 
            $validated['notes'] ?? null
        );

        AdminActivityLog::log(
            $admin,
            'ticket.assigned',
            $ticket,
            ['assigned_to' => $oldAssignee],
            ['assigned_to' => $assignee->id],
            "Assigned ticket {$ticket->ticket_number} to {$assignee->name}"
        );

        // Broadcast ticket update to all admins
        broadcast(new TicketUpdated($ticket->fresh(), 'assigned', $admin->id))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully',
            'data' => $ticket->fresh(['user:id,name,email', 'assignedTo:id,name,email']),
        ]);
    }

    /**
     * Auto-assign ticket to best available support staff
     */
    public function autoAssign(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canHandleSupportTickets()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Support officers cannot use auto-assign
        if ($admin->isSupportOfficer()) {
            return response()->json([
                'success' => false,
                'message' => 'Support officers cannot use auto-assign. Use the "Take Ticket" option instead.',
            ], 403);
        }

        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $result = $ticket->autoAssign();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'No available support staff to assign',
            ], 400);
        }

        AdminActivityLog::log(
            $admin,
            'ticket.auto_assigned',
            $ticket,
            null,
            ['assigned_to' => $ticket->assigned_to],
            "Auto-assigned ticket {$ticket->ticket_number} to {$ticket->assignedTo->name}"
        );

        // Broadcast ticket update to all admins
        broadcast(new TicketUpdated($ticket->fresh(), 'auto_assigned', $admin->id))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Ticket auto-assigned successfully',
            'data' => $ticket->fresh(['user:id,name,email', 'assignedTo:id,name,email']),
        ]);
    }

    /**
     * Self-assign ticket (take ownership)
     */
    public function selfAssign(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canHandleSupportTickets()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $oldAssignee = $ticket->assigned_to;
        $ticket->assignToWithTracking($admin, $admin, 'self', 'Self-assigned');

        AdminActivityLog::log(
            $admin,
            'ticket.self_assigned',
            $ticket,
            ['assigned_to' => $oldAssignee],
            ['assigned_to' => $admin->id],
            "Self-assigned ticket {$ticket->ticket_number}"
        );

        // Broadcast ticket update to all admins
        broadcast(new TicketUpdated($ticket->fresh(), 'self_assigned', $admin->id))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned to you',
            'data' => $ticket->fresh(['user:id,name,email', 'assignedTo:id,name,email']),
        ]);
    }

    /**
     * Get available support staff for assignment
     */
    public function getAvailableStaff(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canHandleSupportTickets()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $staff = User::supportStaff()
            ->where('is_active', true)
            ->select('id', 'name', 'email', 'role', 'is_online', 'last_seen_at', 'active_tickets_count')
            ->orderBy('is_online', 'desc')
            ->orderBy('active_tickets_count', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $staff,
        ]);
    }

    /**
     * Update ticket status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canHandleSupportTickets()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,waiting_on_customer,resolved,closed',
        ]);

        $oldStatus = $ticket->status;
        
        switch ($validated['status']) {
            case 'resolved':
                $ticket->resolve();
                break;
            case 'closed':
                $ticket->close();
                break;
            case 'open':
                $ticket->reopen();
                break;
            default:
                $ticket->update(['status' => $validated['status']]);
        }

        AdminActivityLog::log(
            $admin,
            'ticket.status_changed',
            $ticket,
            ['status' => $oldStatus],
            ['status' => $validated['status']],
            "Changed ticket {$ticket->ticket_number} status from {$oldStatus} to {$validated['status']}"
        );

        // Broadcast ticket update to all admins
        broadcast(new TicketUpdated($ticket->fresh(), 'status_changed', $admin->id))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully',
            'data' => $ticket->fresh(),
        ]);
    }

    /**
     * Add a message to a ticket
     */
    public function addMessage(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canHandleSupportTickets()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $validated = $request->validate([
            'message' => 'required|string',
            'is_internal' => 'sometimes|boolean',
        ]);

        $message = $ticket->addMessage(
            $admin,
            $validated['message'],
            $validated['is_internal'] ?? false
        );

        // Auto-assign if not assigned
        if (!$ticket->assigned_to) {
            $ticket->assignTo($admin);
        }

        // Broadcast message added to all admins
        broadcast(new TicketUpdated($ticket->fresh(), 'new_message', $admin->id))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Message added successfully',
            'data' => $message->load('user:id,name,email,role'),
        ]);
    }

    /**
     * Update ticket priority
     */
    public function updatePriority(Request $request, int $id): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canHandleSupportTickets()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $validated = $request->validate([
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        $oldPriority = $ticket->priority;
        $ticket->update(['priority' => $validated['priority']]);

        AdminActivityLog::log(
            $admin,
            'ticket.priority_changed',
            $ticket,
            ['priority' => $oldPriority],
            ['priority' => $validated['priority']],
            "Changed ticket {$ticket->ticket_number} priority from {$oldPriority} to {$validated['priority']}"
        );

        // Broadcast ticket update to all admins
        broadcast(new TicketUpdated($ticket->fresh(), 'priority_changed', $admin->id))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Ticket priority updated successfully',
            'data' => $ticket->fresh(),
        ]);
    }

    /**
     * Get ticket statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $admin = $this->getAuthenticatedUser($request);
        
        if (!$admin || !$admin->canHandleSupportTickets()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $stats = [
            'by_status' => [
                'open' => SupportTicket::where('status', 'open')->count(),
                'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
                'waiting_on_customer' => SupportTicket::where('status', 'waiting_on_customer')->count(),
                'resolved' => SupportTicket::where('status', 'resolved')->count(),
                'closed' => SupportTicket::where('status', 'closed')->count(),
            ],
            'by_priority' => [
                'low' => SupportTicket::open()->where('priority', 'low')->count(),
                'medium' => SupportTicket::open()->where('priority', 'medium')->count(),
                'high' => SupportTicket::open()->where('priority', 'high')->count(),
                'urgent' => SupportTicket::open()->where('priority', 'urgent')->count(),
            ],
            'by_category' => [
                'billing' => SupportTicket::open()->where('category', 'billing')->count(),
                'technical' => SupportTicket::open()->where('category', 'technical')->count(),
                'feature_request' => SupportTicket::open()->where('category', 'feature_request')->count(),
                'account' => SupportTicket::open()->where('category', 'account')->count(),
                'other' => SupportTicket::open()->where('category', 'other')->count(),
            ],
            'unassigned' => SupportTicket::open()->unassigned()->count(),
            'my_tickets' => SupportTicket::open()->where('assigned_to', $admin->id)->count(),
            'average_resolution_time' => $this->calculateAverageResolutionTime(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Calculate average resolution time in hours
     */
    private function calculateAverageResolutionTime(): ?float
    {
        $resolved = SupportTicket::whereNotNull('resolved_at')
            ->whereNotNull('created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
            ->first();

        return $resolved->avg_hours ? round($resolved->avg_hours, 1) : null;
    }

    /**
     * Helper to get authenticated user
     */
    private function getAuthenticatedUser(Request $request): ?User
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }

        if (str_contains($token, '|')) {
            [$id, $plainTextToken] = explode('|', $token, 2);
            $hashedToken = hash('sha256', $plainTextToken);
        } else {
            $hashedToken = hash('sha256', $token);
        }

        $tokenRecord = \Laravel\Sanctum\PersonalAccessToken::where('token', $hashedToken)->first();

        if (!$tokenRecord) {
            return null;
        }

        return User::find($tokenRecord->tokenable_id);
    }
}
