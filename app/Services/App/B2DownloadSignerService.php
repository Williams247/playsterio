<?php

namespace App\Services\App;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class B2DownloadSignerService
{
    private const AUTHORIZE_ENDPOINT = 'https://api.backblazeb2.com/b2api/v2/b2_authorize_account';
    private const DOWNLOAD_AUTH_ENDPOINT = '/b2api/v2/b2_get_download_authorization';
    private const TOKEN_REFRESH_BUFFER_SECONDS = 60;

    private static ?array $cachedAuth = null;

    public function signDownload(array $payload, ?string $userId = null): array
    {
        $fileName = $this->resolveFileName($payload);
        $this->guardAllowedPrefix($fileName);
        $ttl = max(1, (int) config('backblaze.signed_url_ttl_seconds', 3600));

        $auth = $this->getAuthorization();
        $bucketId = (string) config('backblaze.bucket_id');
        $bucketName = (string) config('backblaze.bucket_name');

        $downloadToken = $this->getDownloadAuthorizationToken(
            authorization: $auth,
            fileName: $fileName,
            ttlSeconds: $ttl,
            requestedBucketId: $bucketId
        );

        $signedUrl = rtrim((string) $auth['downloadUrl'], '/')
            . '/file/' . rawurlencode($bucketName)
            . '/' . $this->encodeObjectPath($fileName)
            . '?Authorization=' . rawurlencode($downloadToken);

        Log::info('storage_signing_success', [
            'event' => 'storage_signing_success',
            'user_id' => $userId,
            'bucket_name' => $bucketName,
            'bucket_id_hint' => $this->maskValue($bucketId),
            'key_id_hint' => $this->maskValue((string) config('backblaze.key_id')),
        ]);

        return [
            'url' => $signedUrl,
            'fileName' => $fileName,
            'expiresIn' => $ttl,
        ];
    }

    public function resolveFileName(array $payload): string
    {
        $sourceUrl = isset($payload['sourceUrl']) ? trim((string) $payload['sourceUrl']) : '';
        $fileName = isset($payload['fileName']) ? trim((string) $payload['fileName']) : '';

        if ($sourceUrl === '' && $fileName === '') {
            throw new RuntimeException('Provide sourceUrl or fileName.', 400);
        }

        if ($sourceUrl !== '') {
            $fileName = $this->extractFileNameFromSourceUrl($sourceUrl);
        }

        if ($fileName === '' || str_starts_with($fileName, '/')) {
            throw new RuntimeException('Invalid fileName.', 400);
        }

        if (str_contains($fileName, '..')) {
            throw new RuntimeException('Invalid fileName path.', 400);
        }

        return ltrim($fileName, '/');
    }

    public function encodeObjectPath(string $fileName): string
    {
        $segments = explode('/', $fileName);
        $encodedSegments = array_map(
            static fn (string $segment): string => rawurlencode($segment),
            $segments
        );

        return implode('/', $encodedSegments);
    }

    public function healthCheck(): array
    {
        $config = $this->getConfigWithHints();
        $missing = [];

        foreach (['key_id', 'application_key', 'bucket_id', 'bucket_name'] as $required) {
            if (empty(config('backblaze.' . $required))) {
                $missing[] = $required;
            }
        }

        if ($missing !== []) {
            return [
                'status' => 'error',
                'ok' => false,
                'message' => 'Backblaze config missing.',
                'missing' => $missing,
                'config' => $config,
            ];
        }

        try {
            $auth = $this->getAuthorization();

            return [
                'status' => 'ok',
                'ok' => true,
                'message' => 'Backblaze signing health check passed.',
                'config' => array_merge($config, [
                    'allowed_bucket_id_hint' => $this->maskValue((string) ($auth['allowed']['bucketId'] ?? '')),
                ]),
            ];
        } catch (\Throwable $e) {
            Log::error('storage_signing_health_failed', [
                'event' => 'storage_signing_health_failed',
                'error' => $this->safeError($e),
                'status_code' => (int) $e->getCode(),
                'config' => $config,
            ]);

            return [
                'status' => 'error',
                'ok' => false,
                'message' => 'Backblaze auth check failed.',
                'error' => $this->safeError($e),
                'config' => $config,
            ];
        }
    }

    private function extractFileNameFromSourceUrl(string $sourceUrl): string
    {
        $parsed = parse_url($sourceUrl);
        $host = strtolower((string) ($parsed['host'] ?? ''));
        $path = (string) ($parsed['path'] ?? '');
        $bucketName = (string) config('backblaze.bucket_name');

        if ($host === '' || !str_contains($host, 'backblazeb2.com')) {
            throw new RuntimeException('sourceUrl host must be backblazeb2.com.', 400);
        }

        $prefix = '/file/' . $bucketName . '/';
        if (!str_starts_with($path, $prefix)) {
            throw new RuntimeException('sourceUrl bucket does not match configured bucket.', 400);
        }

        $fileName = substr($path, strlen($prefix));
        $decoded = rawurldecode((string) $fileName);

        return trim($decoded);
    }

    private function guardAllowedPrefix(string $fileName): void
    {
        $allowedPrefix = trim((string) config('backblaze.allowed_prefix', ''));
        if ($allowedPrefix === '') {
            return;
        }

        $normalizedPrefix = rtrim(ltrim($allowedPrefix, '/'), '/') . '/';
        if (!str_starts_with($fileName, $normalizedPrefix)) {
            throw new RuntimeException('File is outside allowed prefix.', 403);
        }
    }

    private function getAuthorization(bool $forceRefresh = false): array
    {
        $cached = self::$cachedAuth;
        if (!$forceRefresh && $cached !== null && ((int) $cached['expires_at']) > (time() + self::TOKEN_REFRESH_BUFFER_SECONDS)) {
            return $cached['payload'];
        }

        $keyId = (string) config('backblaze.key_id');
        $applicationKey = (string) config('backblaze.application_key');

        if ($keyId === '' || $applicationKey === '') {
            throw new RuntimeException('Backblaze credentials are not configured.', 500);
        }

        $response = $this->postWithRetries(
            url: self::AUTHORIZE_ENDPOINT,
            options: [
                'auth' => [$keyId, $applicationKey],
            ]
        );

        if (!$response->successful()) {
            throw new RuntimeException('Backblaze authorization failed.', $response->status());
        }

        $payload = $response->json();
        if (!is_array($payload) || empty($payload['apiUrl']) || empty($payload['downloadUrl']) || empty($payload['authorizationToken'])) {
            throw new RuntimeException('Backblaze authorization returned incomplete payload.', 500);
        }

        self::$cachedAuth = [
            'expires_at' => time() + 24 * 60 * 60,
            'payload' => $payload,
        ];

        return $payload;
    }

    private function getDownloadAuthorizationToken(array $authorization, string $fileName, int $ttlSeconds, string $requestedBucketId): string
    {
        $allowedBucketId = (string) ($authorization['allowed']['bucketId'] ?? '');
        $bucketId = $requestedBucketId !== '' ? $requestedBucketId : $allowedBucketId;

        $response = $this->requestDownloadToken(
            apiUrl: (string) $authorization['apiUrl'],
            authorizationToken: (string) $authorization['authorizationToken'],
            bucketId: $bucketId,
            fileName: $fileName,
            ttlSeconds: $ttlSeconds
        );

        if (!$response->successful() && $bucketId !== $allowedBucketId && $allowedBucketId !== '') {
            $errorCode = (string) $response->json('code', '');
            if ($errorCode === 'bad_bucket_id' || str_contains((string) $response->body(), 'bucket')) {
                Log::warning('storage_signing_bucket_fallback', [
                    'event' => 'storage_signing_bucket_fallback',
                    'requested_bucket_id_hint' => $this->maskValue($requestedBucketId),
                    'fallback_bucket_id_hint' => $this->maskValue($allowedBucketId),
                    'status_code' => $response->status(),
                ]);

                $response = $this->requestDownloadToken(
                    apiUrl: (string) $authorization['apiUrl'],
                    authorizationToken: (string) $authorization['authorizationToken'],
                    bucketId: $allowedBucketId,
                    fileName: $fileName,
                    ttlSeconds: $ttlSeconds
                );
            }
        }

        if (!$response->successful()) {
            throw new RuntimeException('Failed to create download authorization.', $response->status());
        }

        $token = (string) $response->json('authorizationToken', '');
        if ($token === '') {
            throw new RuntimeException('Backblaze download authorization token missing.', 500);
        }

        return $token;
    }

    private function requestDownloadToken(
        string $apiUrl,
        string $authorizationToken,
        string $bucketId,
        string $fileName,
        int $ttlSeconds
    ): Response {
        return $this->postWithRetries(
            url: rtrim($apiUrl, '/') . self::DOWNLOAD_AUTH_ENDPOINT,
            options: [
                'token' => $authorizationToken,
            ],
            payload: [
                'bucketId' => $bucketId,
                'fileNamePrefix' => $fileName,
                'validDurationInSeconds' => $ttlSeconds,
            ]
        );
    }

    private function postWithRetries(string $url, array $options = [], array $payload = []): Response
    {
        $attempt = 0;
        $maxAttempts = 3;
        $delaysMs = [250, 600];
        $lastException = null;
        $lastResponse = null;

        while ($attempt < $maxAttempts) {
            try {
                $request = Http::acceptJson();

                if (isset($options['auth'])) {
                    $request = $request->withBasicAuth($options['auth'][0], $options['auth'][1]);
                }

                if (isset($options['token'])) {
                    $request = $request->withToken($options['token']);
                }

                $lastResponse = $request->post($url, $payload);
            } catch (ConnectionException $e) {
                $lastException = $e;
                $lastResponse = null;
            }

            $attempt++;

            if ($lastResponse !== null && !$lastResponse->serverError()) {
                return $lastResponse;
            }

            if ($attempt < $maxAttempts) {
                usleep($delaysMs[$attempt - 1] * 1000);
            }
        }

        if ($lastResponse !== null) {
            return $lastResponse;
        }

        throw new RuntimeException('Unable to reach Backblaze endpoint.', 500, $lastException);
    }

    private function getConfigWithHints(): array
    {
        return [
            'bucket_name' => (string) config('backblaze.bucket_name'),
            'bucket_id_hint' => $this->maskValue((string) config('backblaze.bucket_id')),
            'key_id_hint' => $this->maskValue((string) config('backblaze.key_id')),
            'allowed_prefix' => (string) config('backblaze.allowed_prefix', ''),
            'ttl_seconds' => (int) config('backblaze.signed_url_ttl_seconds', 3600),
        ];
    }

    private function maskValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 10) {
            return substr($value, 0, 2) . '***';
        }

        return substr($value, 0, 6) . '***' . substr($value, -4);
    }

    private function safeError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains(strtolower($message), 'authorization')) {
            return 'authorization_failed';
        }

        if (str_contains(strtolower($message), 'bucket')) {
            return 'bucket_configuration_error';
        }

        return 'signing_unavailable';
    }
}

?>
