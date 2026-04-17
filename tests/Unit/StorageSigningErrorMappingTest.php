<?php

namespace Tests\Unit;

use App\Http\Controllers\StorageSigningController;
use App\Services\App\B2DownloadSignerService;
use Tests\TestCase;

class StorageSigningErrorMappingTest extends TestCase
{
    public function test_error_mapping_uses_expected_http_statuses(): void
    {
        $controller = new StorageSigningController(app(B2DownloadSignerService::class));
        $reflection = new \ReflectionMethod($controller, 'mapStatusCode');
        $reflection->setAccessible(true);

        $this->assertSame(400, $reflection->invoke($controller, 400));
        $this->assertSame(401, $reflection->invoke($controller, 401));
        $this->assertSame(429, $reflection->invoke($controller, 429));
        $this->assertSame(500, $reflection->invoke($controller, 418));
    }
}
