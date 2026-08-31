<?php

namespace Modules\McpServer\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\McpServer\OAuth\OAuthServerConfiguration;

final class OAuthMetadataController extends Controller
{
    private $configuration;

    public function __construct(OAuthServerConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function authorizationServer()
    {
        $this->requireEnabled();

        return response()->json($this->configuration->authorizationServerMetadata())
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function protectedResource()
    {
        $this->requireEnabled();

        return response()->json($this->configuration->protectedResourceMetadata())
            ->header('Cache-Control', 'public, max-age=300');
    }

    private function requireEnabled(): void
    {
        if (!$this->configuration->enabled() || !config('mcpserver.enabled', false)) {
            abort(404);
        }
    }
}
