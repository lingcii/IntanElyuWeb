<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Custom Brevo HTTP API transport for Laravel/Symfony Mailer.
 *
 * Sends emails via Brevo's REST API over HTTPS (port 443),
 * bypassing Railway's SMTP port blocks (25, 465, 587).
 */
class BrevoApiTransport extends AbstractTransport
{
    private const API_URL = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $fromAddresses = $email->getFrom();
        $from = count($fromAddresses) > 0 ? $fromAddresses[0] : null;

        $payload = [
            'sender' => [
                'name'  => $from?->getName() ?: config('mail.from.name'),
                'email' => $from?->getAddress() ?: config('mail.from.address'),
            ],
            'to' => array_map(
                fn (Address $addr) => [
                    'email' => $addr->getAddress(),
                    'name'  => $addr->getName() ?: $addr->getAddress(),
                ],
                $email->getTo()
            ),
            'subject'     => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
            'textContent' => $email->getTextBody(),
        ];

        // Include CC/BCC if present
        if ($cc = $email->getCc()) {
            $payload['cc'] = array_map(
                fn (Address $addr) => ['email' => $addr->getAddress(), 'name' => $addr->getName()],
                $cc
            );
        }
        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = array_map(
                fn (Address $addr) => ['email' => $addr->getAddress(), 'name' => $addr->getName()],
                $bcc
            );
        }

        $response = Http::withOptions(['verify' => false])
            ->withHeaders([
                'api-key'      => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post(self::API_URL, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Brevo API error (' . $response->status() . '): ' . $response->body()
            );
        }
    }

    public function __toString(): string
    {
        return 'brevo-api';
    }
}
