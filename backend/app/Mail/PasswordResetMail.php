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
        $frontendBase = rtrim(env('APP_FRONTEND_URL', config('app.url', 'http://localhost')), '/');
        if (str_ends_with(strtolower($frontendBase), '/reset-password.php')) {
            $resetFile = $frontendBase;
        } elseif (str_ends_with(strtolower($frontendBase), '/frontend') || str_contains($frontendBase, 'railway.app')) {
            $resetFile = $frontendBase . '/reset-password.php';
        } else {
            $resetFile = $frontendBase . '/Frontend/updatedweb/Frontend/reset-password.php';
        }
        $resetUrl = $resetFile . '?token=' . $this->token . '&email=' . urlencode($this->user->email);

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
