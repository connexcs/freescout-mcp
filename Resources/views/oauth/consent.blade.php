@extends('layouts.app')

@section('title_full', __('Authorize MCP client'))

@section('content')
    <div class="section-heading">{{ __('Authorize MCP client') }}</div>
    <div class="row-container">
        <div class="row"><div class="col-md-8 col-lg-6">
            <p><strong>{{ $clientName }}</strong> {{ __('is requesting access to FreeScout as') }} <strong>{{ Auth::user()->email }}</strong>.</p>
            <p class="text-help">{{ __('Client ID') }}: <code>{{ $clientId }}</code></p>
            <p class="text-help">{{ __('The authorization result will be sent to') }} <strong>{{ $redirectHost }}</strong>.</p>
            @if ($loopback)
                <div class="alert alert-warning">{{ __('This client uses a local callback. Only continue if you started the connection on this device.') }}</div>
            @endif
            <h4>{{ __('Requested access') }}</h4>
            <ul>
                <li>{{ __('Read tickets and other data already visible to this FreeScout account') }}</li>
                @if (in_array('mcp:write', $scopes, true))
                    <li>{{ __('Create internal notes, update tickets, and create unsent draft replies when write tools are enabled') }}</li>
                @endif
            </ul>
            <p>{{ __('FreeScout permissions continue to apply to every request. You can revoke this connection from the MCP Tokens page.') }}</p>
            <form method="POST" action="{{ route('mcpserver.oauth.decide') }}">
                {{ csrf_field() }}
                <input type="hidden" name="request_handle" value="{{ $handle }}">
                <button type="submit" name="decision" value="approve" class="btn btn-primary">{{ __('Authorize') }}</button>
                <button type="submit" name="decision" value="deny" class="btn btn-default">{{ __('Cancel') }}</button>
            </form>
        </div></div>
    </div>
@endsection
