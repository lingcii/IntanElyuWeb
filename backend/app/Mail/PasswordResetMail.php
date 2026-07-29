<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public User $user, public string $token)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔐 Reset Your Password - Intan Elyu',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Hybrid URL: use Railway frontend URL when deployed, localhost when running locally
        $appUrl = config('app.url', 'http://localhost');
        $isRailway = str_contains($appUrl, 'railway.app');
        $frontendBase = $isRailway
            ? env('APP_FRONTEND_URL', 'https://intanelyuweb-frontend-production.up.railway.app')
            : 'http://localhost:8080';
        $resetUrl = $frontendBase . '?view=reset-password&token=' . $this->token . '&email=' . urlencode($this->user->email);

        return new Content(
            view: 'emails.password_reset',
            with: [
                'userName'  => $this->user->name,
                'userEmail' => $this->user->email,
                'resetUrl'  => $resetUrl,
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
