<?php

namespace App\Http\Controllers;

use App\Services\App\B2DownloadSignerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StorageSigningController extends Controller
{
    public function __construct(private B2DownloadSignerService $signerService)
    {
    }

    public function signDownload(Request $request)
    {
        try {
            $payload = $request->validate([
                'sourceUrl' => 'nullable|string|max:4096',
                'fileName' => 'nullable|string|max:2048',
            ]);

            $result = $this->signerService->signDownload($payload, (string) ($request->user()?->id ?? ''));

            return response()->json($result, 200);
        } catch (RuntimeException $e) {
            $status = $this->mapStatusCode($e->getCode());

            Log::warning('storage_signing_failed', [
                'event' => 'storage_signing_failed',
                'user_id' => (string) ($request->user()?->id ?? ''),
                'bucket_name' => (string) config('backblaze.bucket_name'),
                'bucket_id_hint' => $this->maskValue((string) config('backblaze.bucket_id')),
                'key_id_hint' => $this->maskValue((string) config('backblaze.key_id')),
                'status_code' => $status,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $status >= 500 ? 'Unable to sign download URL right now.' : $e->getMessage(),
            ], $status);
        } catch (\Throwable $e) {
            Log::error('storage_signing_unexpected_failure', [
                'event' => 'storage_signing_unexpected_failure',
                'user_id' => (string) ($request->user()?->id ?? ''),
                'bucket_name' => (string) config('backblaze.bucket_name'),
                'bucket_id_hint' => $this->maskValue((string) config('backblaze.bucket_id')),
                'key_id_hint' => $this->maskValue((string) config('backblaze.key_id')),
                'status_code' => 500,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to sign download URL right now.',
            ], 500);
        }
    }

    public function storageSigningHealth()
    {
        $result = $this->signerService->healthCheck();
        $status = ($result['ok'] ?? false) ? 200 : 500;

        return response()->json($result, $status);
    }

    private function mapStatusCode(int $code): int
    {
        if (in_array($code, [400, 401, 403, 429], true)) {
            return $code;
        }

        return 500;
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
}

?>
