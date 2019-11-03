<?php

namespace Cronqvist\Api\Services\Auth;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Token;

class AuthService
{

    /**
     * Login via Laravel Passport with only a username & password
     *
     * @param string $username
     * @param string $password
     * @param string $scopes
     * @return \Illuminate\Http\JsonResponse
     */

    public function login($username, $password, $scopes = '*')
    {
        // Send an internal API request to get an access token
        $client = DB::table('oauth_clients')
            ->where('password_client', true)
            ->first();

        // Make sure a Password Client exists in the DB
        if(!$client) {
            return response()->json([
                'error' => 'Laravel Passport is not setup properly.',
            ], 500);
        }

        $data = [
            'grant_type' => 'password',
            'client_id' => $client->id,
            'client_secret' => $client->secret,
            'username' => $username,
            'password' => $password,
            'scopes' => $scopes,
        ];

        $request = Request::create('/oauth/token', 'POST', $data);
        $response = app()->handle($request);
        /** @var $response \Illuminate\Http\Response */

        // Add "expires_at" to the response
        $content = @json_decode($response->getContent(), true);
        if(isset($content['access_token'])) {
            $bearerToken = $content['access_token'];
            $tokenId = (new \Lcobucci\JWT\Parser())->parse($bearerToken)->getHeader('jti');
            $token = Token::query()->findOrFail($tokenId);
            $content['expires_at'] = $token->expires_at->toAtomString();
            $response->setContent($content);
        }

        return $response;
    }

    /**
     * Retrieve the currently logged in user
     *
     * @return \App\User
     */

    public function user()
    {
        $user = auth('api')->user();
        return $user;
    }

    /**
     * Logout a user
     *
     * @param \App\User|null $user
     * @param bool $logoutEverywhere
     * @return \Illuminate\Http\JsonResponse
     */

    public function logout(User $user, $logoutEverywhere = false)
    {
        $accessTokens = $logoutEverywhere ? $user->tokens() : [$user->token()];

        foreach($accessTokens as $accessToken) {
            $refreshToken = DB::table('oauth_refresh_tokens')
                ->where('access_token_id', $accessToken->id)
                ->update([
                    'revoked' => true
                ]);

            $accessToken->revoke();
        }

        return response()->json(['message' => 'Successfully logged out']);
    }
}
