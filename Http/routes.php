<?php

Route::group([
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\\McpServer\\Http\\Controllers',
], function () {
    Route::match(['POST', 'OPTIONS'], '/mcp', 'McpController@handle')
        ->name('mcpserver.endpoint');
});
