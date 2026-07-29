<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * CloudflareR2Service
 *
 * Uploads / deletes files on Cloudflare R2 using the S3-compatible REST API
 * with AWS Signature V4 signing. No extra Composer packages required —
 * Guzzle is already bundled with Laravel.
 *
 * Required .env keys:
 *   CLOUDFLARE_R2_ACCESS_KEY_ID
 *   CLOUDFLARE_R2_SECRET_KEY
 *   CLOUDFLARE_R2_BUCKET
 *   CLOUDFLARE_R2_ENDPOINT        (e.g. https://<account>.r2.cloudflarestorage.com)
 *   CLOUDFLARE_R2_URL             (public base URL, e.g. same as endpoint + /<bucket>)
 */
class CloudflareR2Service
{
    private string $accessKey;
    private string $secretKey;
    private string $bucket;
    private string $endpoint;   // https://<account-id>.r2.cloudflarestorage.com
    private string $publicUrl;  // public base URL for generating viewable URLs
    private string $region = 'auto';
    private Client $client;

    public function __construct()
    {
        $this->accessKey = env('CLOUDFLARE_R2_ACCESS_KEY_ID', '');
        $this->secretKey = env('CLOUDFLARE_R2_SECRET_KEY', '');
        $this->bucket    = env('CLOUDFLARE_R2_BUCKET', 'intan-elyu-media');
        $this->endpoint  = rtrim(env('CLOUDFLARE_R2_ENDPOINT', ''), '/');
        $this->publicUrl = rtrim(env('CLOUDFLARE_R2_URL', $this->endpoint . '/' . $this->bucket), '/');

        $this->client = new Client([
            'timeout'         => 10,
            'connect_timeout' => 5,
            'verify'          => false, // R2 uses valid certs; set true in production if possible
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Upload a file to Cloudflare R2.
     *
     * @param  UploadedFile  $file    The uploaded file from the request
     * @param  string        $folder  Destination folder inside the bucket (e.g. 'tourist_spots')
     * @param  string|null   $filename  Optional custom filename; auto-generated if null
     * @return string  Full public URL of the uploaded file
     *
     * @throws \RuntimeException on upload failure
     */
    public function upload(UploadedFile $file, string $folder, ?string $filename = null): string
    {
        $filename  = $filename ?? $this->generateFilename($file);
        $objectKey = trim($folder, '/') . '/' . $filename;
        $body      = file_get_contents($file->getRealPath());
        $mimeType  = $file->getMimeType() ?? 'application/octet-stream';

        $this->putObject($objectKey, $body, $mimeType);

        return $this->publicUrl($objectKey);
    }

    /**
     * Upload raw content (string/binary) to R2.
     *
     * @param  string  $objectKey  Full path inside bucket (e.g. 'tourist_spots/file.jpg')
     * @param  string  $content    Raw file content
     * @param  string  $mimeType   MIME type of the content
     * @return string  Full public URL
     */
    public function uploadContent(string $objectKey, string $content, string $mimeType = 'application/octet-stream'): string
    {
        $this->putObject($objectKey, $content, $mimeType);
        return $this->publicUrl($objectKey);
    }

    /**
     * Download/fetch an object from R2.
     *
     * @param  string  $objectKey  Full path inside bucket (e.g. 'tourist_spots/file.jpg')
     * @return string|null  Raw binary content or null if not found/error
     */
    public function getObject(string $objectKey): ?string
    {
        try {
            $url     = $this->endpoint . '/' . $this->bucket . '/' . ltrim($objectKey, '/');
            $headers = $this->sign('GET', $objectKey, '', '');
            $response = $this->client->get($url, ['headers' => $headers]);

            if ($response->getStatusCode() === 200) {
                return (string) $response->getBody();
            }
        } catch (GuzzleException $e) {
            Log::warning("[R2] getObject failed for [{$objectKey}]: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Delete an object from R2.
     *
     * @param  string  $objectKey  Full path inside bucket (e.g. 'tourist_spots/file.jpg')
     */
    public function delete(string $objectKey): void
    {
        try {
            $url     = $this->endpoint . '/' . $this->bucket . '/' . ltrim($objectKey, '/');
            $headers = $this->sign('DELETE', $objectKey, '', '');
            $this->client->delete($url, ['headers' => $headers]);
        } catch (GuzzleException $e) {
            Log::warning("[R2] Delete failed for [{$objectKey}]: " . $e->getMessage());
        }
    }

    /**
     * Generate the public URL for an object key.
     */
    public function publicUrl(string $objectKey): string
    {
        return $this->publicUrl . '/' . ltrim($objectKey, '/');
    }

    /**
     * Check if the service is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->accessKey)
            && !empty($this->secretKey)
            && !empty($this->bucket)
            && !empty($this->endpoint);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * PUT an object into R2 with a full AWS-Sig-V4 signed request.
     */
    private function putObject(string $objectKey, string $body, string $mimeType): void
    {
        $url     = $this->endpoint . '/' . $this->bucket . '/' . ltrim($objectKey, '/');
        $headers = $this->sign('PUT', $objectKey, $body, $mimeType);

        try {
            $response = $this->client->put($url, [
                'headers' => $headers,
                'body'    => $body,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException("[R2] Upload failed with HTTP {$statusCode}");
            }

            Log::info("[R2] Uploaded [{$objectKey}] → HTTP {$statusCode}");
        } catch (GuzzleException $e) {
            Log::error("[R2] Upload error for [{$objectKey}]: " . $e->getMessage());
            throw new \RuntimeException("[R2] Upload failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate AWS Signature V4 headers for the given request.
     */
    private function sign(string $method, string $objectKey, string $body, string $contentType): array
    {
        $service     = 's3';
        $region      = $this->region;
        $accessKey   = $this->accessKey;
        $secretKey   = $this->secretKey;
        $bucket      = $this->bucket;
        $host        = parse_url($this->endpoint, PHP_URL_HOST);

        $amzDate     = gmdate('Ymd\THis\Z');
        $dateStamp   = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        // Canonical URI: /<bucket>/<objectKey>
        $canonicalUri = '/' . $bucket . '/' . ltrim($objectKey, '/');

        // Canonical headers (must be sorted alphabetically)
        $canonicalHeaders = implode("\n", [
            'content-type:' . $contentType,
            'host:' . $host,
            'x-amz-content-sha256:' . $payloadHash,
            'x-amz-date:' . $amzDate,
        ]) . "\n";

        $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            '',  // empty canonical query string
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        // String to sign
        $credentialScope = implode('/', [$dateStamp, $region, $service, 'aws4_request']);
        $stringToSign    = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        // Signing key
        $signingKey = $this->hmac(
            $this->hmac(
                $this->hmac(
                    $this->hmac('AWS4' . $secretKey, $dateStamp),
                    $region
                ),
                $service
            ),
            'aws4_request'
        );

        $signature = bin2hex(hash_hmac('sha256', $stringToSign, $signingKey, true));

        $authorizationHeader = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $accessKey,
            $credentialScope,
            $signedHeaders,
            $signature
        );

        return [
            'Authorization'        => $authorizationHeader,
            'Content-Type'         => $contentType,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date'           => $amzDate,
            'Host'                 => $host,
        ];
    }

    /**
     * HMAC-SHA256 helper — returns raw binary.
     */
    private function hmac(string $key, string $data): string
    {
        return hash_hmac('sha256', $data, $key, true);
    }

    /**
     * Generate a unique filename for an uploaded file.
     */
    private function generateFilename(UploadedFile $file): string
    {
        return uniqid('', true) . '.' . $file->getClientOriginalExtension();
    }
}
