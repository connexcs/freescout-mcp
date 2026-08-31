<?php

Route::group([
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\\McpServer\\Http\\Controllers',
], function () {
    Route::get('/.well-known/oauth-authorization-server', 'OAuthMetadataController@authorizationServer')
        ->name('mcpserver.oauth.authorization_server');
    Route::get('/.well-known/oauth-protected-resource', 'OAuthMetadataController@protectedResource')
        ->name('mcpserver.oauth.protected_resource');
    Route::get('/.well-known/oauth-protected-resource/mcp', 'OAuthMetadataController@protectedResource');

    Route::post('/mcp/oauth/register', 'OAuthController@register')->middleware('throttle:30,1')->name('mcpserver.oauth.register');
    Route::post('/mcp/oauth/token', 'OAuthController@token')->middleware('throttle:30,1')->name('mcpserver.oauth.token');
    Route::post('/mcp/oauth/revoke', 'OAuthController@revoke')->middleware('throttle:30,1')->name('mcpserver.oauth.revoke');
});

Route::group([
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\\McpServer\\Http\\Controllers',
    'middleware' => ['web', 'auth'],
], function () {
    Route::get('/mcp/oauth/authorize', 'OAuthController@authorization')->name('mcpserver.oauth.authorize');
    Route::post('/mcp/oauth/authorize', 'OAuthController@decide')->name('mcpserver.oauth.decide');
});

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
    Route::delete('/users/mcp-oauth/{id}/{family}', 'TokenController@revokeOAuth')->name('mcpserver.oauth.revoke_user');
});
