<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeUserMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  \App\Models\User  $user           The newly created user
     * @param  string            $plainPassword  The temporary password assigned by the admin
     * @param  string            $loginUrl       The URL to the system's login page
     */
    public function __construct(
        public User   $user,
        public string $plainPassword,
        public string $loginUrl = '',
    ) {
        // Default login URL if none provided — hybrid: Railway when deployed, localhost otherwise
        if (empty($this->loginUrl)) {
            $appUrl = config('app.url', 'http://localhost');
            $isRailway = str_contains($appUrl, 'railway.app');
            $this->loginUrl = $isRailway
                ? env('APP_FRONTEND_URL', 'https://intanelyuweb-frontend-production.up.railway.app')
                : 'http://localhost:8080';
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to the INTAN ELYU Tourism Management System',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $roleLabel = match (strtolower($this->user->role)) {
            'lupto'                                     => 'LUPTO (La Union Provincial Tourism Office)',
            'municipal', 'municipal_mto', 'mto'        => 'Municipal Tourism Office (MTO)',
            'picto', 'pitco'                            => 'PICTO (Provincial Information and Communications Technology Office)',
            default                                     => ucwords(str_replace('_', ' ', $this->user->role)),
        };

        return new Content(
            view: 'emails.welcome_user',
            with: [
                'userName'      => $this->user->name,
                'userEmail'     => $this->user->email,
                'userRole'      => $roleLabel,
                'plainPassword' => $this->plainPassword,
                'municipalName' => $this->user->municipality?->name,
                'registeredAt'  => now()->format('F j, Y \a\t g:i A'),
                'loginUrl'      => $this->loginUrl,
                'appName'       => 'INTAN ELYU Tourism Management System',
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
