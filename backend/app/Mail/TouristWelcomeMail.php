<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TouristWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public User $user)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎉 Welcome to Intan-Elyu! Your Account is Ready',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tourist_welcome',
            with: [
                'userName'      => $this->user->name,
                'userEmail'     => $this->user->email,
                'userRole'      => ucfirst($this->user->role),
                'userId'        => $this->user->id,
                'registeredAt'  => now()->format('F j, Y \a\t g:i A'),
                'appName'       => config('app.name', 'Intan-Elyu Tourism'),
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
