<?php

namespace App\Http\Middleware;

use App\Services\Crypto\PayloadEncryptionService;
use App\Services\Json\JsonResponse;
use Closure;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Supports encrypted save-music bodies:
 *   headers: X-Body-Encrypted: 1, kp: <shared secret>
 *   body:    { "asset": "<base64(iv+ciphertext+tag)>" }
 *
 * If encryption header is not set, request passes through as regular JSON.
 */
class DecryptSaveMusicPayload
{
    public function handle(Request $request, Closure $next): Response
    {
        $isEncrypted = filter_var($request->header('X-Body-Encrypted', '0'), FILTER_VALIDATE_BOOLEAN)
            || $request->header('X-Body-Encrypted') === '1';

        if (!$isEncrypted) {
            return $next($request);
        }

        $asset = $request->input('asset');
        if (!is_string($asset) || $asset === '') {
            return JsonResponse::bad_request('Encrypted body requires a non-empty "asset" string.', null);
        }

        $kp = (string) $request->header('kp', '');
        if ($kp === '') {
            return JsonResponse::bad_request('Encrypted body requires "kp" header.', null);
        }

        try {
            $plaintext = $this->decryptWithSupportedVariants($asset, $kp);
            $decoded = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return JsonResponse::bad_request('Decrypted asset is not valid JSON.', null);
        } catch (Throwable) {
            return JsonResponse::bad_request(
                'Could not decrypt encrypted body. Ensure the client sends supported asset format and key derivation.',
                [
                    'supported_asset_formats' => [
                        'base64(iv+ciphertext+tag)',
                        'json-string: {"iv":"...","ciphertext":"...","tag":"..."}',
                        'dot-separated: iv.ciphertext.tag',
                    ],
                    'expected_key_derivation' => [
                        'primary' => 'sha256(kp) raw bytes (32 bytes)',
                        'fallback_compat' => [
                            'first-32-chars of sha256(kp) hex string',
                            'kp treated as base64-encoded key',
                            'kp treated as hex-encoded key',
                        ],
                    ],
                ]
            );
        }

        if (!is_array($decoded)) {
            return JsonResponse::bad_request('Decrypted asset must be a JSON object.', null);
        }

        $request->merge($decoded);
        $request->request->remove('asset');

        return $next($request);
    }

    private function decryptWithSupportedVariants(string $asset, string $kp): string
    {
        $payloads = $this->expandAssetVariants($asset);
        $keys = $this->deriveCandidateKeys($kp);

        foreach ($keys as $key) {
            $service = new PayloadEncryptionService($key);
            foreach ($payloads as $payload) {
                try {
                    return $service->decrypt($payload);
                } catch (Throwable) {
                    // Try next candidate.
                }
            }
        }

        throw new \RuntimeException('No decryption strategy matched.');
    }

    private function expandAssetVariants(string $asset): array
    {
        $variants = [$asset];

        $decodedJson = json_decode($asset, true);
        if (is_array($decodedJson)) {
            $iv = $decodedJson['iv'] ?? null;
            $ciphertext = $decodedJson['ciphertext'] ?? ($decodedJson['cipher'] ?? null);
            $tag = $decodedJson['tag'] ?? null;
            if (is_string($iv) && is_string($ciphertext) && is_string($tag)) {
                $ivBin = base64_decode($iv, true);
                $cipherBin = base64_decode($ciphertext, true);
                $tagBin = base64_decode($tag, true);
                if ($ivBin !== false && $cipherBin !== false && $tagBin !== false) {
                    $variants[] = base64_encode($ivBin.$cipherBin.$tagBin);
                }
            }
        }

        if (str_contains($asset, '.')) {
            $parts = explode('.', $asset);
            if (count($parts) === 3) {
                [$iv, $ciphertext, $tag] = $parts;
                $ivBin = base64_decode($iv, true);
                $cipherBin = base64_decode($ciphertext, true);
                $tagBin = base64_decode($tag, true);
                if ($ivBin !== false && $cipherBin !== false && $tagBin !== false) {
                    $variants[] = base64_encode($ivBin.$cipherBin.$tagBin);
                }
            }
        }

        return array_values(array_unique($variants));
    }

    private function deriveCandidateKeys(string $kp): array
    {
        $keys = [];

        // Preferred: raw binary SHA-256 digest (32 bytes).
        $keys[] = hash('sha256', $kp, true);

        // Compatibility: 32-char ASCII from SHA-256 hex.
        $keys[] = substr(hash('sha256', $kp, false), 0, 32);

        $base64Key = base64_decode($kp, true);
        if ($base64Key !== false && strlen($base64Key) === 32) {
            $keys[] = $base64Key;
        }

        if (ctype_xdigit($kp) && strlen($kp) === 64) {
            $hexKey = hex2bin($kp);
            if ($hexKey !== false) {
                $keys[] = $hexKey;
            }
        }

        return array_values(array_unique($keys));
    }
}

?>
