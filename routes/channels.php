<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Private channel for individual admin notifications
 * admin.notifications.{userId}
 */
Broadcast::channel('admin.notifications.{userId}', function (User $user, int $userId) {
    // User can only listen to their own notifications
    return $user->id === $userId && $user->canHandleSupportTickets();
});

/**
 * Private channel for all ticket updates
 * All support staff can listen to this channel
 */
Broadcast::channel('admin.tickets', function (User $user) {
    return $user->canHandleSupportTickets();
});

/**
 * Private channel for staff status updates (online/offline)
 * All support staff can listen to this channel
 */
Broadcast::channel('admin.staff-status', function (User $user) {
    return $user->canHandleSupportTickets();
});

/**
 * Private channel for individual ticket updates
 * Users can listen to their own tickets, support staff can listen to all tickets
 */
Broadcast::channel('ticket.{ticketId}', function (User $user, int $ticketId) {
    // Support staff can listen to any ticket
    if ($user->canHandleSupportTickets()) {
        return true;
    }
    
    // Regular users can only listen to their own tickets
    $ticket = \App\Models\SupportTicket::find($ticketId);
    return $ticket && $ticket->user_id === $user->id;
});
