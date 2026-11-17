<?php
namespace Networkteam\Neos\FrontendLogin\Security\SecondFactor;

/***************************************************************
 *  (c) 2026 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Cookie;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Cryptography\HashService;

/**
 * Remembers browsers that passed the second factor, so the code can be skipped for a configured time
 *
 * The cookie is signed and holds a hash of the account, the expiry and a fingerprint of the account's credentials
 * source plus the revocation marker of the second factor provider. Changing the password or the marker invalidates
 * every remembered browser of the account.
 *
 * @Flow\Scope("singleton")
 */
class RememberedBrowserService
{
    const COOKIE_NAME_PREFIX = 'Nwt_Neos_FrontendLogin_2FA_';

    /**
     * @Flow\Inject
     * @var HashService
     */
    protected $hashService;

    /**
     * @Flow\Inject
     * @var SecondFactorProviderInterface
     */
    protected $secondFactorProvider;

    /**
     * ISO 8601 duration (e.g. "P30D"), empty disables the feature
     *
     * @Flow\InjectConfiguration(path="secondFactor.rememberBrowser.duration")
     * @var string|null
     */
    protected $duration;

    /**
     * Whether the cookie gets the Secure flag; only disable it for local development without HTTPS
     *
     * @Flow\InjectConfiguration(path="secondFactor.rememberBrowser.cookie.secure")
     * @var bool
     */
    protected $secureCookie = true;

    public function isEnabled(): bool
    {
        return !empty($this->duration);
    }

    public function createCookie(Account $account, ?\DateTimeImmutable $now = null): Cookie
    {
        $now = $now ?? new \DateTimeImmutable();
        $expiry = $now->add(new \DateInterval($this->duration));
        $value = $this->hashService->appendHmac($this->createPayload($account, $expiry->getTimestamp()));

        return new Cookie(
            $this->getCookieName($account),
            $value,
            $expiry->getTimestamp(),
            $expiry->getTimestamp() - $now->getTimestamp(),
            null,
            '/',
            (bool)$this->secureCookie,
            true,
            Cookie::SAMESITE_LAX
        );
    }

    /**
     * @param array $cookies Cookie parameters of the request
     */
    public function isRemembered(Account $account, array $cookies, ?\DateTimeImmutable $now = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $value = $cookies[$this->getCookieName($account)] ?? null;
        if (!is_string($value)) {
            return false;
        }

        try {
            $encodedPayload = $this->hashService->validateAndStripHmac($value);
        } catch (\Exception $e) {
            return false;
        }

        $expiry = $this->getExpiryFromPayload($encodedPayload);
        if ($expiry === null) {
            return false;
        }

        $now = $now ?? new \DateTimeImmutable();

        return $expiry > $now->getTimestamp()
            && hash_equals($this->createPayload($account, $expiry), $encodedPayload);
    }

    /**
     * Build the URL-safe base64 encoded payload of the cookie, before it gets signed
     */
    protected function createPayload(Account $account, int $expiry): string
    {
        $payload = json_encode([
            'a' => $this->getAccountHash($account),
            'e' => $expiry,
            'f' => $this->getFingerprint($account)
        ]);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * Read the expiry timestamp from a payload built by createPayload(), null if it cannot be decoded
     */
    protected function getExpiryFromPayload(string $encodedPayload): ?int
    {
        $data = json_decode((string)base64_decode(strtr($encodedPayload, '-_', '+/')), true);
        if (!is_array($data) || !isset($data['e'])) {
            return null;
        }

        return (int)$data['e'];
    }

    protected function getCookieName(Account $account): string
    {
        return self::COOKIE_NAME_PREFIX . substr($this->getAccountHash($account), 0, 16);
    }

    protected function getAccountHash(Account $account): string
    {
        return $this->hashService->generateHmac(
            'account|' . $account->getAuthenticationProviderName() . '|' . $account->getAccountIdentifier()
        );
    }

    protected function getFingerprint(Account $account): string
    {
        return $this->hashService->generateHmac(
            'fingerprint|' . (string)$account->getCredentialsSource() . '|' . $this->secondFactorProvider->getRememberRevocationMarker($account)
        );
    }
}
