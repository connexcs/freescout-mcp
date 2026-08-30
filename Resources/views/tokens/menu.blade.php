<li @if (Route::currentRouteName() == 'mcpserver.tokens.index')class="active"@endif>
    <a href="{{ route('mcpserver.tokens.index', ['id' => $user->id]) }}">
        <i class="glyphicon glyphicon-link"></i> {{ __('MCP Tokens') }}
    </a>
</li>
