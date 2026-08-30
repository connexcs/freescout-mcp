<?php

Route::group([
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\\McpServer\\Http\\Controllers',
    'middleware' => ['mcpserver.auth', 'mcpserver.throttle'],
], function () {
    Route::match(['POST', 'OPTIONS'], '/mcp', 'McpController@handle')
        ->name('mcpserver.endpoint');
});

Route::group([
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\\McpServer\\Http\\Controllers',
    'middleware' => ['web', 'auth'],
], function () {
    Route::get('/users/mcp-tokens/{id}', 'TokenController@index')->name('mcpserver.tokens.index');
    Route::post('/users/mcp-tokens/{id}', 'TokenController@create')->name('mcpserver.tokens.create');
    Route::delete('/users/mcp-tokens/{id}/{tokenId}', 'TokenController@revoke')->name('mcpserver.tokens.revoke');
    Route::delete('/users/mcp-tokens/{id}', 'TokenController@revokeAll')->name('mcpserver.tokens.revoke_all');
});
