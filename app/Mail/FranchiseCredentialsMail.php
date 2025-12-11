<?php

namespace App\Mail;

use App\Models\Franchise;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FranchiseCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public Franchise $franchise;
    public string $password;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Franchise $franchise, string $password)
    {
        $this->user = $user;
        $this->franchise = $franchise;
        $this->password = $password;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . config('app.name') . ' - Your Account Credentials',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.franchise-credentials',
            with: [
                'userName' => $this->user->name,
                'userEmail' => $this->user->email,
                'password' => $this->password,
                'franchiseName' => $this->franchise->name,
                'loginUrl' => config('app.frontend_url', 'http://localhost:3000') . '/auth/login',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
