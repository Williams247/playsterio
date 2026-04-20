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

        $service = new PayloadEncryptionService(hash('sha256', $kp, true));

        try {
            $plaintext = $service->decrypt($asset);
            $decoded = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return JsonResponse::bad_request('Decrypted asset is not valid JSON.', null);
        } catch (Throwable) {
            return JsonResponse::bad_request('Could not decrypt encrypted body.', null);
        }

        if (!is_array($decoded)) {
            return JsonResponse::bad_request('Decrypted asset must be a JSON object.', null);
        }

        $request->merge($decoded);
        $request->request->remove('asset');

        return $next($request);
    }
}

?>
