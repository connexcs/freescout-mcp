<form class="form-horizontal margin-top margin-bottom" method="POST" action="">
    {{ csrf_field() }}

    <div class="form-group">
        <label for="mcpserver-personal-tokens" class="col-sm-3 control-label">{{ __('Personal MCP tokens') }}</label>
        <div class="col-sm-7">
            <div class="onoffswitch-wrap">
                <div class="onoffswitch">
                    <input type="checkbox" name="settings[mcpserver.personal_tokens_enabled]" value="1" id="mcpserver-personal-tokens" class="onoffswitch-checkbox" @if (old('settings[mcpserver.personal_tokens_enabled]', $settings['mcpserver.personal_tokens_enabled']))checked="checked"@endif>
                    <label class="onoffswitch-label" for="mcpserver-personal-tokens"></label>
                </div>
            </div>
            <p class="form-help">{{ __('Disabling this immediately rejects every personal MCP token without deleting it.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label for="mcpserver-regular-users" class="col-sm-3 control-label">{{ __('Regular users') }}</label>
        <div class="col-sm-7">
            <div class="onoffswitch-wrap">
                <div class="onoffswitch">
                    <input type="checkbox" name="settings[mcpserver.allow_non_admin_tokens]" value="1" id="mcpserver-regular-users" class="onoffswitch-checkbox" @if (old('settings[mcpserver.allow_non_admin_tokens]', $settings['mcpserver.allow_non_admin_tokens']))checked="checked"@endif>
                    <label class="onoffswitch-label" for="mcpserver-regular-users"></label>
                </div>
            </div>
            <p class="form-help">{{ __('When disabled, only active administrators can create and use personal MCP tokens.') }}</p>
        </div>
    </div>

    <div class="form-group{{ $errors->has('settings.mcpserver\.token_lifetime_days') ? ' has-error' : '' }}">
        <label for="mcpserver-token-lifetime" class="col-sm-3 control-label">{{ __('Token lifetime') }}</label>
        <div class="col-sm-7">
            <div class="input-group input-sized">
                <input id="mcpserver-token-lifetime" type="number" min="0" max="3650" name="settings[mcpserver.token_lifetime_days]" class="form-control" value="{{ old('settings[mcpserver.token_lifetime_days]', $settings['mcpserver.token_lifetime_days']) }}" required>
                <span class="input-group-addon">{{ __('days') }}</span>
            </div>
            <p class="form-help">{{ __('Use 0 for no automatic expiry. This applies to newly created tokens only.') }}</p>
            @include('partials/field_error', ['field' => 'settings.mcpserver\.token_lifetime_days'])
        </div>
    </div>

    <div class="form-group">
        <label for="mcpserver-mutations" class="col-sm-3 control-label">{{ __('Write tools') }}</label>
        <div class="col-sm-7">
            <div class="onoffswitch-wrap">
                <div class="onoffswitch">
                    <input type="checkbox" name="settings[mcpserver.mutations_enabled]" value="1" id="mcpserver-mutations" class="onoffswitch-checkbox" @if (old('settings[mcpserver.mutations_enabled]', $settings['mcpserver.mutations_enabled']))checked="checked"@endif>
                    <label class="onoffswitch-label" for="mcpserver-mutations"></label>
                </div>
            </div>
            <p class="form-help">{{ __('Requires MCP_SERVER_MUTATIONS_ENABLED=true. Enables audited note, ticket update, and draft-reply tools. Draft replies are never sent.') }}</p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('MCP endpoint') }}</label>
        <div class="col-sm-7">
            <p class="form-control-static"><code>{{ route('mcpserver.endpoint') }}</code></p>
            @if (config('mcpserver.enabled', false))
                <p class="text-success">{{ __('The endpoint is enabled.') }}</p>
            @else
                <p class="text-warning">{{ __('The endpoint is disabled by MCP_SERVER_ENABLED. Tokens can be prepared, but authentication will remain unavailable until it is enabled.') }}</p>
            @endif
        </div>
    </div>

    <div class="form-group margin-top">
        <div class="col-sm-7 col-sm-offset-3">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </div>
</form>
