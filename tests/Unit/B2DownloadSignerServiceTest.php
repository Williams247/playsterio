<?php

namespace Tests\Unit;

use App\Services\App\B2DownloadSignerService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class B2DownloadSignerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('backblaze.key_id', 'key-id-123456');
        config()->set('backblaze.application_key', 'app-key-abcdef');
        config()->set('backblaze.bucket_id', 'bad-bucket-id');
        config()->set('backblaze.bucket_name', 'audio-bucket');
        config()->set('backblaze.signed_url_ttl_seconds', 3600);
        config()->set('backblaze.allowed_prefix', '');

        $this->resetAuthCache();
    }

    public function test_source_url_is_parsed_and_validated(): void
    {
        $service = app(B2DownloadSignerService::class);

        $result = $service->resolveFileName([
            'sourceUrl' => 'https://f005.backblazeb2.com/file/audio-bucket/folder/track%201.mp3',
        ]);

        $this->assertSame('folder/track 1.mp3', $result);
    }

    public function test_invalid_source_host_is_rejected(): void
    {
        $service = app(B2DownloadSignerService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sourceUrl host must be backblazeb2.com.');

        $service->resolveFileName([
            'sourceUrl' => 'https://example.com/file/audio-bucket/folder/track.mp3',
        ]);
    }

    public function test_object_path_is_encoded_segment_by_segment(): void
    {
        $service = app(B2DownloadSignerService::class);

        $encoded = $service->encodeObjectPath('folder one/track #1.mp3');

        $this->assertSame('folder%20one/track%20%231.mp3', $encoded);
    }

    public function test_signing_falls_back_to_allowed_bucket_id_when_env_bucket_is_stale(): void
    {
        Http::fake([
            'https://api.backblazeb2.com/b2api/v2/b2_authorize_account' => Http::response([
                'authorizationToken' => 'master-auth-token',
                'apiUrl' => 'https://api005.backblazeb2.com',
                'downloadUrl' => 'https://f005.backblazeb2.com',
                'allowed' => [
                    'bucketId' => 'good-bucket-id',
                ],
            ], 200),
            'https://api005.backblazeb2.com/b2api/v2/b2_get_download_authorization' => Http::sequence()
                ->push(['code' => 'bad_bucket_id'], 400)
                ->push(['authorizationToken' => 'download-token-123'], 200),
        ]);

        $service = app(B2DownloadSignerService::class);
        $signed = $service->signDownload(['fileName' => 'audio/song.mp3'], 'user-1');

        $this->assertSame('audio/song.mp3', $signed['fileName']);
        $this->assertSame(3600, $signed['expiresIn']);
        $this->assertStringContainsString('Authorization=download-token-123', $signed['url']);
    }

    private function resetAuthCache(): void
    {
        $reflection = new \ReflectionClass(B2DownloadSignerService::class);
        $property = $reflection->getProperty('cachedAuth');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}
