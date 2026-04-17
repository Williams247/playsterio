<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\App\B2DownloadSignerService;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SignDownloadEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('backblaze.key_id', 'key-id-123456');
        config()->set('backblaze.application_key', 'app-key-abcdef');
        config()->set('backblaze.bucket_id', 'bucket-id-123');
        config()->set('backblaze.bucket_name', 'audio-bucket');
        config()->set('backblaze.signed_url_ttl_seconds', 3600);
        config()->set('backblaze.allowed_prefix', '');

        $this->resetAuthCache();
    }

    public function test_sign_download_requires_authentication(): void
    {
        $response = $this->postJson('/api/sign-download', [
            'fileName' => 'audio/song.mp3',
        ]);

        $response->assertStatus(401);
    }

    public function test_sign_download_rejects_invalid_source_url_host(): void
    {
        Sanctum::actingAs(new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        $response = $this->postJson('/api/sign-download', [
            'sourceUrl' => 'https://invalid-host.com/file/audio-bucket/audio/song.mp3',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'sourceUrl host must be backblazeb2.com.',
            ]);
    }

    public function test_sign_download_returns_signed_url_when_valid(): void
    {
        Sanctum::actingAs(new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]));

        Http::fake([
            'https://api.backblazeb2.com/b2api/v2/b2_authorize_account' => Http::response([
                'authorizationToken' => 'master-auth-token',
                'apiUrl' => 'https://api005.backblazeb2.com',
                'downloadUrl' => 'https://f005.backblazeb2.com',
                'allowed' => [
                    'bucketId' => 'bucket-id-123',
                ],
            ], 200),
            'https://api005.backblazeb2.com/b2api/v2/b2_get_download_authorization' => Http::response([
                'authorizationToken' => 'download-auth-token',
            ], 200),
        ]);

        $response = $this->postJson('/api/sign-download', [
            'fileName' => 'audio/song.mp3',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'fileName' => 'audio/song.mp3',
                'expiresIn' => 3600,
            ]);
    }

    private function resetAuthCache(): void
    {
        $reflection = new \ReflectionClass(B2DownloadSignerService::class);
        $property = $reflection->getProperty('cachedAuth');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}
