<?php

namespace Cronqvist\Api\Services\Auth;

use Cronqvist\Api\Exception\ApiException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Token;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;

class AuthService
{

    /**
     * Login via Laravel Passport with only a username & password
     *
     * @param string $username
     * @param string $password
     * @param string $scopes
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
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

        $passportController = app()->make(AccessTokenController::class);
        /** @var $passportController AccessTokenController */
        $request = Request::create('/oauth/token', 'POST', $data);
        $psr = (new DiactorosFactory)->createRequest($request); // See: CheckClientCredentials::handle() from Passport
        $response = $passportController->issueToken($psr);

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
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user()
    {
        $user = auth('api')->user();
        return $user;
    }

    /**
     * Logout a user
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @param bool $logoutEverywhere
     * @return \Illuminate\Http\JsonResponse
     * @throws \Cronqvist\Api\Exception\ApiException
     */
    public function logout(Authenticatable $user, $logoutEverywhere = false)
    {
        if(!in_array(HasApiTokens::class, class_uses_recursive($user))) {
            throw new ApiException("The trait 'HasApiTokens' is missing in '" . get_class($user));
        }

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
