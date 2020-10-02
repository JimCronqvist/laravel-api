<?php

namespace Cronqvist\Api\Services\Auth;

use Carbon\Carbon;
use Cronqvist\Api\Exception\ApiException;
use Cronqvist\Api\Exception\ApiPassportException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Laravel\Passport\Bridge\User;
use Laravel\Passport\Exceptions\OAuthServerException;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Token;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Laravel\Passport\Bridge\UserRepository;

class AuthService
{
    /**
     * Name of the cookie where we store the refresh token
     *
     * @var string
     */
    public static $refreshToken = 'refreshToken';

    /**
     * Name of the cookie where we store the access token.
     * Note: This should not be 'laravel_token'.
     *
     * @var string
     */
    public static $accessToken = 'accessToken';

    /**
     * Determine if we should create the new access token as a cookie on top of the json response.
     *
     * @var bool
     */
    public static $useAccessTokenCookie = false;

    /**
     * Specify how many seconds we should cache refresh token requests, used to prevent race conditions on frontend side
     *
     * @var int
     */
    public static $cacheRefreshTokenRequestsForSeconds = 15;

    /**
     * Define which relations to load for the user endpoint. To be able to utilize 'whenLoaded' in the UserResource.
     *
     * @var bool
     */
    public static $loadRelations = [];


    /**
     * Get the OAuth2 params for the password client
     *
     * @param string $grant
     * @return array
     * @throws ApiPassportException
     */

    protected function getOAuthParams($grant)
    {
        // Find the first available Password Client
        $client = DB::table('oauth_clients')
            ->where('password_client', true)
            ->first();

        // Make sure a Password Client exists in the DB
        if(!$client) {
            throw new ApiPassportException("Laravel Passport is not setup properly. No password grant client exists.
                Run 'php artisan passport:client --password' to set it up.
            ");
        }

        return [
            'grant_type' => $grant,
            'client_id' => $client->id,
            'client_secret' => $client->secret,
        ];
    }

    /**
     * Convert the Laravel request to a PSR-7 implementation which is compatible with the League OAuth2 library
     *
     * @param Request $request
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function makeRequestPsr7(Request $request)
    {
        // See: CheckClientCredentials::handle() from Passport
        // See: TokenGuard::getPsrRequestViaBearerToken() from Passport

        $psr = (new PsrHttpFactory(
            new ServerRequestFactory,
            new StreamFactory,
            new UploadedFileFactory,
            new ResponseFactory
        ))->createRequest($request);

        return $psr;
    }

    /**
     * Get a token from Laravel Passport
     *
     * @param array $data
     * @return Response
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */

    protected function requestPassportAccessToken(array $data)
    {
        // Send an internal API request to get an access token
        $passportController = app()->make(AccessTokenController::class);
        /** @var $passportController AccessTokenController */
        $request = Request::create('/oauth/token', 'POST', $data);
        $psr = $this->makeRequestPsr7($request);
        return $passportController->issueToken($psr);
    }

    /**
     * Parse the Passport token response, set the refresh token cookie
     *
     * @param Response $response
     * @return Response
     */
    protected function processPassportAccessToken(Response $response)
    {
        // Add "expires_at" to the response
        $content = @json_decode($response->getContent(), true);
        if(isset($content['access_token'])) {
            $tokenId = (new \Lcobucci\JWT\Parser())
                ->parse($content['access_token'])
                ->getClaim('jti');
            $token = Token::query()->findOrFail($tokenId);
            $content['expires_at'] = $token->expires_at->toAtomString();
            $response->setContent($content);

            // Create the access token as a cookie as well if we are supposed to
            if(static::$useAccessTokenCookie === true) {
                $response->cookie($this->makeAccessTokenCookie($content['access_token']));
            }
        }

        // Remove the refresh token and set it to a cookie instead
        if(isset($content['refresh_token'])) {
            $response->cookie($this->makeRefreshTokenCookie($content['refresh_token']));
            unset($content['refresh_token']);
            $response->setContent($content);
        }

        return $response;
    }

    /**
     * Get the relative path for the refresh token route
     *
     * @return string
     */
    protected function getRefreshRoutePath()
    {
        return route('authRefreshToken', [], false);
    }

    /**
     * Create the refresh token cookie
     *
     * @param $refreshToken
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    protected function makeRefreshTokenCookie($refreshToken)
    {
        $interval = Passport::refreshTokensExpireIn();
        $expire = Carbon::now()->add($interval);
        $minutes = (int) ceil(Carbon::now()->diffInSeconds($expire, false) / 60);

        return Cookie::make(
            static::$refreshToken,
            $refreshToken,
            $minutes,
            $this->getRefreshRoutePath(),
            null,
            request()->secure(),
            true,
            config('api.same_site', 'lax'),
        );
    }

    /**
     * Create the access token cookie
     *
     * @param $accessToken
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    protected function makeAccessTokenCookie($accessToken)
    {
        $interval = Passport::tokensExpireIn();
        $expire = Carbon::now()->add($interval);
        $expire = $expire->addMonth(); // Add a bit time to the cookie, to be able to catch the token as 'expired'.
        $minutes = (int) ceil(Carbon::now()->diffInSeconds($expire, false) / 60);

        return Cookie::make(
            static::$accessToken,
            $accessToken,
            $minutes,
            '/',
            null,
            request()->secure(),
            true,
            config('api.same_site', 'lax'),
        );
    }

    /**
     * Check if Passport is configured correctly
     *
     * @throws ApiPassportException
     */
    protected function checkPassportSetup()
    {
        // Check that passport encryption keys has been generated
        if(empty(config('passport.private_key')) || empty(config('passport.public_key'))) {
            [$publicKey, $privateKey] = [
                Passport::keyPath('oauth-public.key'),
                Passport::keyPath('oauth-private.key'),
            ];
            if(!is_readable($publicKey) || !is_readable($privateKey)) {
                throw new ApiPassportException("Passport encryption keys are missing. 
                Please run 'php artisan passport:install'");
            }
        }

        // Check that the config/auth.php has been configured
        if(config('auth.guards.api.driver') != 'passport') {
            throw new ApiPassportException("The api guard driver in config/auth.php has not been set to 'passport'.");
        }

        // Ensure that the 'HasApiTokens' trait has been added to the User model
        $userClass = config('auth.providers.users.model');
        if(!in_array(HasApiTokens::class, class_uses_recursive($userClass))) {
            throw new ApiPassportException("The trait 'HasApiTokens' is missing in '" . $userClass);
        }
    }

    /**
     * Login via Laravel Passport with only a username & password
     *
     * @param string $username
     * @param string $password
     * @param string $scopes
     * @return Response
     * @throws \Illuminate\Contracts\Container\BindingResolutionException|ApiPassportException
     */
    public function login($username, $password, $scopes = '*')
    {
        $this->checkPassportSetup();

        if(empty($username) || empty($password)) {
            throw new BadRequestHttpException('Invalid request. Username and Password must be provided.');
        }

        $data = $this->getOAuthParams('password') + [
            'username' => $username,
            'password' => $password,
            'scopes' => $scopes,
        ];

        try {
            $response = $this->requestPassportAccessToken($data);
        } catch (OAuthServerException $exception) {
            if($exception->getCode() == 10) {
                throw new AuthenticationException('Incorrect user credentials.');
            }
        }

        return $this->processPassportAccessToken($response);
    }

    /**
     * Login via Laravel Passport with only a username & password
     *
     * @param string $refreshToken
     * @return Response
     * @throws ApiPassportException
     */
    public function refresh($refreshToken)
    {
        if(empty($refreshToken)) {
            throw new BadRequestHttpException('Invalid request. No refresh token provided.');
        }

        // Utilize a cache to handle race conditions, as a refresh token can only be refreshed one time.
        $key = 'refreshToken:' . $refreshToken;
        $response = Cache::remember($key, static::$cacheRefreshTokenRequestsForSeconds, function() use($refreshToken) {
            $data = $this->getOAuthParams('refresh_token') + ['refresh_token' => $refreshToken];
            return $this->processPassportAccessToken($this->requestPassportAccessToken($data));
        });

        // Update the expires_in, as it could have come from the cache and be a few seconds off because of that.
        /** @var $response Response */
        if($response->getStatusCode() == 200) {
            $json = $response->getOriginalContent();
            $json['expires_in'] = Carbon::now()->diffInSeconds(new Carbon($json['expires_at']), false);
            if($json['expires_in'] < 0) {
                // Access Token has expired..
                throw new BadRequestHttpException('The access token has already expired');
            }
            $response->setContent($json);
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

        // Load relations if specified
        foreach(static::$loadRelations as $relation) {
            $user->{$relation};
        }

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

        return response()
            ->json(['message' => 'Successfully logged out'])
            ->cookie(Cookie::forget(static::$accessToken))
            ->cookie(Cookie::forget(static::$refreshToken, $this->getRefreshRoutePath()));
    }

    /**
     * Send password reset link
     *
     * @param string $email
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendPasswordResetLink($email)
    {
        if(empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('An email was not provided for the password reset link');
        }

        if(!ResetPassword::$createUrlCallback) {
            ResetPassword::createUrlUsing(function($user, $token) {
                $uri = config('api.forgot_password_request_uri');
                return url(str_replace(['{token}', '{email}'], [$token, $user->email], $uri));
            });
        }

        return response()->json([
            'message' => Password::sendResetLink(['email' => $email])
        ]);
    }

    /**
     * Reset the password
     *
     * @param string $email
     * @param string $token
     * @param string $password The new password that will be stored
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset($email, $token, $password)
    {
        $storePassword = function($user, $password) {
            $user->password = Hash::make($password);
            $user->save();
        };

        $response = Password::reset([
            'email' => $email,
            'token' => $token,
            'password' => $password
        ], $storePassword);

        return response()->json([
            'message' => $response
        ]);
    }

    public static function ensureAuthenticated()
    {
        $psr = (new static())->makeRequestPsr7(request());
        $leagueServer = app()->make(ResourceServer::class);
        /** @var $leagueServer ResourceServer */
        $leagueServer->validateAuthenticatedRequest($psr); // See: BearerTokenValidator::validateAuthorization()
    }

    /**
     * Perform a login for a specific user, which returns the normal access and refresh token, etc.
     *
     * @param Authenticatable $user
     * @param string $scopes
     * @return Response
     * @throws ApiPassportException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function loginAs(Authenticatable $user, $scopes = '*')
    {
        // Fake the expected UserRepository to just return the user we set, rather than fetching by credentials.
        $fakeUserRepository = new class(app()->make(Hasher::class)) extends UserRepository {
            protected $user;
            public function setUser(Authenticatable $user)
            {
                $this->user = $user;
                return $this;
            }
            public function getUserEntityByUserCredentials($username, $password, $grantType, ClientEntityInterface $clientEntity)
            {
                return new User($this->user->getAuthIdentifier());
            }
        };

        // Hijack laravel/passport a bit...
        app()->forgetInstance(AuthorizationServer::class);
        app()->instance(UserRepository::class, $fakeUserRepository->setUser($user));
        app()->make(AuthorizationServer::class);

        // Perform the login with dummy credentials. The user has already been provided by the fakeUserProvider.
        $response = $this->login('x', 'y', $scopes);

        // Cleaning up, just in case the AuthorizationServer is going to be used further in this request (not likely)
        app()->forgetInstance(UserRepository::class);
        app()->forgetInstance(AuthorizationServer::class);

        return $response;
    }
}
