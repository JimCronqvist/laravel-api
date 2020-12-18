<?php

namespace Cronqvist\Api\Services\Auth\Jwt;

use Illuminate\Config\Repository as Config;
use Illuminate\Http\Request;
use Laravel\Passport\Guards\TokenGuard;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\ValidationData;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use \BadMethodCallException;
use \InvalidArgumentException;
use \RuntimeException;

class JwtTokenGuard extends TokenGuard
{
    /**
     * @var \League\OAuth2\Server\CryptKey
     */
    protected CryptKey $publicKey;

    /**
     * Get the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Auth\Authenticatable|mixed|null
     */
    public function user(Request $request)
    {
        $jwt = $this->getValidatedJwt($request);
        if(!$jwt) {
            return null;
        }

        return $this->findUser($jwt);
    }

    /**
     * Get the validated JWT
     *
     * @param Request $request
     * @return string|null
     */
    protected function getValidatedJwt(Request $request)
    {
        $jwt = $this->getJwt($request);
        if(!$jwt) {
            return null;
        }

        $this->publicKey = $this->makeCryptKey('public');

        try {
            $this->validate($jwt);
        } catch(OAuthServerException $exception) {
            return null;
        }

        return $jwt;
    }

    /**
     * Find the user based on the JWT
     *
     * @param $jwt
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function findUser($jwt)
    {
        $token = (new Parser())->parse($jwt);
        $userId = $token->getClaim('sub');
        return $this->provider->retrieveById($userId);
    }

    /**
     * Get the JWT from the request
     *
     * @param Request $request
     * @return array|string
     */
    protected function getJwt(Request $request)
    {
        if ($request->bearerToken()) {
            $header = $request->bearerToken();
            return \trim((string) \preg_replace('/^(?:\s+)?Bearer\s/', '', $header));
        } elseif ($request->cookie(Passport::cookie())) {
            try {
                return $this->decodeJwtTokenCookie($request);
            } catch (\Exception $e) {}
        }
    }

    /**
     * Validate the JWT signature, etc. without the checks against the database, such as if the token has been revoked
     *
     * @see \League\OAuth2\Server\AuthorizationValidators\BearerTokenValidator
     * @param string $jwt
     * @throws \League\OAuth2\Server\Exception\OAuthServerException
     */
    protected function validate(string $jwt)
    {
        try {
            // Attempt to parse and validate the JWT
            $token = (new Parser())->parse($jwt);
            try {
                if ($token->verify(new Sha256(), $this->publicKey->getKeyPath()) === false) {
                    throw OAuthServerException::accessDenied('Access token could not be verified');
                }
            } catch (BadMethodCallException $exception) {
                throw OAuthServerException::accessDenied('Access token is not signed', null, $exception);
            }

            // Ensure access token hasn't expired
            $data = new ValidationData();
            $data->setCurrentTime(\time());

            if ($token->validate($data) === false) {
                throw OAuthServerException::accessDenied('Access token is invalid');
            }
        } catch (InvalidArgumentException $exception) {
            // JWT couldn't be parsed so return the request as is
            throw OAuthServerException::accessDenied($exception->getMessage(), null, $exception);
        } catch (RuntimeException $exception) {
            // JWT couldn't be parsed so return the request as is
            throw OAuthServerException::accessDenied('Error while decoding to JSON', null, $exception);
        }
    }

    /**
     * Create a CryptKey instance without permissions check.
     *
     * @see \Laravel\Passport\PassportServiceProvider
     * @param string $type
     * @return \League\OAuth2\Server\CryptKey
     */
    protected function makeCryptKey($type)
    {
        $key = str_replace('\\n', "\n", app()->make(Config::class)->get('passport.'.$type.'_key'));

        if(!$key) {
            $key = 'file://'.Passport::keyPath('oauth-'.$type.'.key');
        }

        return new CryptKey($key, null, false);
    }
}
