@extends('layouts.app')

@section('title_full', __('MCP Tokens').' - '.$user->first_name.' '.$user->last_name)

@if ($user->id == Auth::user()->id)
    @section('body_attrs')@parent data-own_profile="true" @endsection
@endif

@section('sidebar')
    @include('partials/sidebar_menu_toggle')
    @include('users/sidebar_menu')
@endsection

@section('content')
    <div class="section-heading">{{ __('MCP Tokens') }}</div>

    @include('partials/flash_messages')

    <div class="row-container">
        <div class="row">
            <div class="col-md-11 col-lg-9">
                @if ($plainToken)
                    <div class="alert alert-warning">
                        <p><strong>{{ __('Copy this token now. It will not be shown again.') }}</strong></p>
                        <div class="form-group margin-top">
                            <input type="text" class="form-control" value="{{ $plainToken }}" readonly onclick="this.select()" autocomplete="off">
                        </div>
                        <p class="text-help">
                            {{ __('Endpoint') }}: <code>{{ $endpoint }}</code><br>
                            {{ __('HTTP header') }}: <code>Authorization: Bearer &lt;token&gt;</code>
                        </p>
                    </div>
                @endif

                @if ($canIssue)
                    <form method="POST" action="{{ route('mcpserver.tokens.create', ['id' => $user->id]) }}" class="form-horizontal margin-bottom">
                        {{ csrf_field() }}

                        <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
                            <label for="mcp-token-name" class="col-sm-3 control-label">{{ __('Token name') }}</label>
                            <div class="col-sm-7">
                                <input id="mcp-token-name" type="text" name="name" class="form-control" value="{{ old('name') }}" maxlength="100" placeholder="{{ __('For example: Claude on laptop') }}" required>
                                @include('partials/field_error', ['field' => 'name'])
                                <p class="form-help">
                                    @if ($lifetimeDays > 0)
                                        {{ __('New tokens expire after :days days.', ['days' => $lifetimeDays]) }}
                                    @else
                                        {{ __('New tokens do not expire automatically.') }}
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-sm-7 col-sm-offset-3">
                                <button type="submit" class="btn btn-primary">{{ __('Create token') }}</button>
                            </div>
                        </div>
                    </form>
                @elseif ($user->id == Auth::user()->id)
                    <div class="alert alert-info">
                        {{ __('Personal MCP tokens are disabled for your account by an administrator.') }}
                    </div>
                @endif

                <h3 class="subheader">{{ __('Existing tokens') }}</h3>

                @if (!count($tokens))
                    <p class="text-help">{{ __('No MCP tokens have been created for this user.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Token') }}</th>
                                    <th>{{ __('Created') }}</th>
                                    <th>{{ __('Last used') }}</th>
                                    <th>{{ __('Expires') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tokens as $token)
                                    <tr>
                                        <td>{{ $token->name }}</td>
                                        <td><code>{{ $token->display_prefix }}</code></td>
                                        <td>{{ App\User::dateFormat($token->created_at) }}</td>
                                        <td>
                                            @if ($token->last_used_at)
                                                {{ App\User::dateFormat($token->last_used_at) }}
                                                @if ($token->last_used_ip)<br><span class="text-help">{{ $token->last_used_ip }}</span>@endif
                                            @else
                                                <span class="text-help">{{ __('Never') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($token->expires_at)
                                                {{ App\User::dateFormat($token->expires_at) }}
                                            @else
                                                <span class="text-help">{{ __('Never') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($token->isRevoked())
                                                <span class="label label-default">{{ __('Revoked') }}</span>
                                            @elseif ($token->isExpired())
                                                <span class="label label-warning">{{ __('Expired') }}</span>
                                            @else
                                                <span class="label label-success">{{ __('Active') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if (!$token->isRevoked())
                                                <form method="POST" action="{{ route('mcpserver.tokens.revoke', ['id' => $user->id, 'tokenId' => $token->id]) }}">
                                                    {{ csrf_field() }}
                                                    {{ method_field('DELETE') }}
                                                    <button type="submit" class="btn btn-xs btn-danger">{{ __('Revoke') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($tokens->where('revoked_at', null)->count())
                        <form method="POST" action="{{ route('mcpserver.tokens.revoke_all', ['id' => $user->id]) }}" class="margin-top">
                            {{ csrf_field() }}
                            {{ method_field('DELETE') }}
                            <button type="submit" class="btn btn-danger">{{ __('Revoke all tokens') }}</button>
                        </form>
                    @endif
                @endif

                <h3 class="subheader">{{ __('OAuth connections') }}</h3>
                @if (!count($oauthConnections))
                    <p class="text-help">{{ __('No MCP clients have been authorized through your FreeScout login.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead><tr><th>{{ __('Client') }}</th><th>{{ __('Scopes') }}</th><th>{{ __('Created') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($oauthConnections as $connection)
                                <tr>
                                    <td>{{ $connection->client ? $connection->client->client_name : __('Unknown client') }}</td>
                                    <td><code>{{ $connection->scopes }}</code></td>
                                    <td>{{ App\User::dateFormat($connection->created_at) }}</td>
                                    <td>
                                        @if ($connection->revoked_at)<span class="label label-default">{{ __('Revoked') }}</span>
                                        @elseif ($connection->expires_at && $connection->expires_at->isPast())<span class="label label-warning">{{ __('Expired') }}</span>
                                        @else<span class="label label-success">{{ __('Active') }}</span>@endif
                                    </td>
                                    <td class="text-right">
                                        @if (!$connection->revoked_at)
                                            <form method="POST" action="{{ route('mcpserver.oauth.revoke_user', ['id' => $user->id, 'family' => $connection->family_id]) }}">
                                                {{ csrf_field() }}{{ method_field('DELETE') }}
                                                <button type="submit" class="btn btn-xs btn-danger">{{ __('Revoke') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
