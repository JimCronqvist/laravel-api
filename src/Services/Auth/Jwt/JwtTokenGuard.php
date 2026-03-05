<?php

namespace Cronqvist\Api\Services\Auth\Jwt;

use Illuminate\Config\Repository as Config;
use Illuminate\Http\Request;
use Laravel\Passport\Guards\TokenGuard;
use Laravel\Passport\Passport;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Key\LocalFileReference;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use \DateTimeZone;

class JwtTokenGuard extends TokenGuard
{
    /**
     * @var \League\OAuth2\Server\CryptKey
     */
    protected CryptKey $publicKey;

    /**
     * @var \Lcobucci\JWT\Configuration
     */
    protected Configuration $jwtConfiguration;

    /**
     * Get the authenticated user
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|mixed|null
     */
    public function user()
    {
        if(!is_null($this->user)) {
            return $this->user;
        }

        $jwt = $this->getValidatedJwt();
        if(!$jwt) {
            return null;
        }

        return $this->user = $this->findUser($jwt);
    }

    /**
     * Get the validated JWT
     *
     * @return string|null
     */
    protected function getValidatedJwt()
    {
        $jwt = $this->getJwt();
        if(!$jwt) {
            return null;
        }

        $this->publicKey = $this->makeCryptKey('public');
        $this->initJwtConfiguration();

        try {
            $this->validateJwt($jwt);
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
        $token = $this->jwtConfiguration->parser()->parse($jwt);
        $userId = $token->claims()->get('sub');
        return $this->provider->retrieveById($userId);
    }

    /**
     * Get the JWT from the request
     *
     * @return array|string|null
     */
    protected function getJwt()
    {
        if($this->request->bearerToken()) {
            $header = $this->request->bearerToken();
            return \trim((string) \preg_replace('/^(?:\s+)?Bearer\s/', '', $header));
        } elseif ($this->request->cookie(Passport::cookie())) {
            try {
                return $this->decodeJwtTokenCookie($this->request);
            } catch (\Exception $e) {}
        }
        return null;
    }

    /**
     * Initialise the JWT configuration.
     *
     * @see \League\OAuth2\Server\AuthorizationValidators\BearerTokenValidator::initJwtConfiguration()
     */
    protected function initJwtConfiguration()
    {
        $this->jwtConfiguration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->publicKey->getKeyContents(), (string) $this->publicKey->getPassPhrase())
        );

        $this->jwtConfiguration->setValidationConstraints(
            new LooseValidAt(new SystemClock(new DateTimeZone(\date_default_timezone_get()))),
            new SignedWith(
                new Sha256(),
                InMemory::plainText($this->publicKey->getKeyContents(), (string) $this->publicKey->getPassPhrase())
            )
        );
    }

    /**
     * Validate the JWT signature, etc. without the checks against the database, such as if the token has been revoked
     *
     * @see \League\OAuth2\Server\AuthorizationValidators\BearerTokenValidator::validateAuthorization()
     * @param string $jwt
     * @throws \League\OAuth2\Server\Exception\OAuthServerException
     */
    protected function validateJwt(string $jwt)
    {
        try {
            // Attempt to parse and validate the JWT
            $token = $this->jwtConfiguration->parser()->parse($jwt);

            $constraints = $this->jwtConfiguration->validationConstraints();

            try {
                $this->jwtConfiguration->validator()->assert($token, ...$constraints);
            } catch (RequiredConstraintsViolated $exception) {
                throw OAuthServerException::accessDenied('Access token could not be verified');
            }
        } catch (CannotDecodeContent | InvalidTokenStructure | UnsupportedHeaderFound $exception) {
            throw OAuthServerException::accessDenied($exception->getMessage(), null, $exception);
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
