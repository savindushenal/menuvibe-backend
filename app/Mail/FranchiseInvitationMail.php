<?php

namespace App\Mail;

use App\Models\Franchise;
use App\Models\FranchiseInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FranchiseInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public FranchiseInvitation $invitation;
    public Franchise $franchise;
    public ?string $tempPassword;

    /**
     * Create a new message instance.
     */
    public function __construct(FranchiseInvitation $invitation, Franchise $franchise, ?string $tempPassword = null)
    {
        $this->invitation = $invitation;
        $this->franchise = $franchise;
        $this->tempPassword = $tempPassword;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'re Invited to Join ' . $this->franchise->name . ' on ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $baseUrl = config('app.frontend_url', 'http://localhost:3000');
        
        return new Content(
            view: 'emails.franchise-invitation',
            with: [
                'inviteeName' => $this->invitation->name ?? 'Team Member',
                'email' => $this->invitation->email,
                'franchiseName' => $this->franchise->name,
                'role' => $this->formatRole($this->invitation->role),
                'invitedByName' => $this->invitation->invitedBy?->name ?? 'Admin',
                'tempPassword' => $this->tempPassword,
                'acceptUrl' => $baseUrl . '/auth/accept-invitation?token=' . $this->invitation->token,
                'loginUrl' => $baseUrl . '/auth/login',
                'expiresAt' => $this->invitation->expires_at?->format('F j, Y'),
            ],
        );
    }

    /**
     * Format role name for display
     */
    protected function formatRole(string $role): string
    {
        return match ($role) {
            'franchise_owner' => 'Franchise Owner',
            'franchise_admin' => 'Franchise Administrator',
            'branch_manager' => 'Branch Manager',
            'staff' => 'Staff Member',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
