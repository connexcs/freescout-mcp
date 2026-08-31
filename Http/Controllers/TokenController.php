<?php

namespace Modules\McpServer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\McpServer\Entities\McpToken;
use Modules\McpServer\Entities\McpOAuthToken;
use Modules\McpServer\Security\TokenIssuer;
use Modules\McpServer\Security\TokenManagementPolicy;
use Modules\McpServer\Security\TokenPolicy;
use Validator;

final class TokenController extends Controller
{
    private $issuer;
    private $policy;
    private $management;

    public function __construct(TokenIssuer $issuer, TokenPolicy $policy, TokenManagementPolicy $management)
    {
        $this->issuer = $issuer;
        $this->policy = $policy;
        $this->management = $management;
    }

    public function index($id)
    {
        $user = $this->managedUser($id);
        $tokens = McpToken::where('user_id', $user->id)->orderBy('id', 'desc')->get();
        $oauthConnections = McpOAuthToken::with('client')->where('user_id', $user->id)
            ->where('type', 'refresh')->orderBy('id', 'desc')->get()->unique('family_id')->values();

        return view('mcpserver::tokens.index', [
            'user' => $user,
            'users' => $this->sidebarUsers($user->id),
            'tokens' => $tokens,
            'oauthConnections' => $oauthConnections,
            'canIssue' => $this->management->canIssue(auth()->user(), $user),
            'plainToken' => session('mcpserver_plain_token'),
            'endpoint' => route('mcpserver.endpoint'),
            'lifetimeDays' => $this->policy->lifetimeDays(),
        ]);
    }

    public function create($id, Request $request)
    {
        $user = $this->managedUser($id);

        if (!$this->management->canIssue(auth()->user(), $user)) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->route('mcpserver.tokens.index', ['id' => $user->id])
                ->withErrors($validator)
                ->withInput();
        }

        $issued = $this->issuer->issue($user, $request->input('name'));

        return redirect()->route('mcpserver.tokens.index', ['id' => $user->id])
            ->with('flash_success_floating', __('MCP token created. Copy it now; it will not be shown again.'))
            ->with('mcpserver_plain_token', $issued->plainText);
    }

    public function revoke($id, $tokenId)
    {
        $user = $this->managedUser($id);
        $token = McpToken::where('user_id', $user->id)->findOrFail($tokenId);

        if (null === $token->revoked_at) {
            $token->revoked_at = Carbon::now();
            $token->save();
        }

        return redirect()->route('mcpserver.tokens.index', ['id' => $user->id])
            ->with('flash_success_floating', __('MCP token revoked.'));
    }

    public function revokeAll($id)
    {
        $user = $this->managedUser($id);

        McpToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        return redirect()->route('mcpserver.tokens.index', ['id' => $user->id])
            ->with('flash_success_floating', __('All MCP tokens for this user have been revoked.'));
    }

    public function revokeOAuth($id, $family)
    {
        $user = $this->managedUser($id);
        if (1 !== preg_match('/\A[A-Za-z0-9_-]{24}\z/', (string) $family)) {
            abort(404);
        }

        McpOAuthToken::where('user_id', $user->id)->where('family_id', $family)
            ->whereNull('revoked_at')->update(['revoked_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

        return redirect()->route('mcpserver.tokens.index', ['id' => $user->id])
            ->with('flash_success_floating', __('MCP OAuth connection revoked.'));
    }

    private function managedUser($id): User
    {
        $user = User::findOrFail($id);
        $currentUser = auth()->user();

        if (!$this->management->canManage($currentUser, $user)) {
            abort(403);
        }

        return $user;
    }

    private function sidebarUsers($exceptId)
    {
        if (!auth()->user()->isAdmin()) {
            return [];
        }

        return User::sortUsers(User::nonDeleted()->get());
    }
}
