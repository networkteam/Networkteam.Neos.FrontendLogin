<?php
namespace Networkteam\Neos\FrontendLogin\Tests\Unit\Security\SecondFactor;

use Neos\Flow\Http\Cookie;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Cryptography\HashService;
use Neos\Flow\Tests\UnitTestCase;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\RememberedBrowserService;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorProviderInterface;

class RememberedBrowserServiceTest extends UnitTestCase
{
    protected RememberedBrowserService $service;

    protected string $revocationMarker = '';

    protected \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $hashService = new class extends HashService {
            public function generateHmac($string)
            {
                return hash_hmac('sha1', $string, 'test-encryption-key');
            }
        };

        $provider = $this->createMock(SecondFactorProviderInterface::class);
        $provider->method('getRememberRevocationMarker')->willReturnCallback(function () {
            return $this->revocationMarker;
        });

        $this->service = new RememberedBrowserService();
        $this->inject($this->service, 'hashService', $hashService);
        $this->inject($this->service, 'secondFactorProvider', $provider);
        $this->inject($this->service, 'duration', 'P30D');

        $this->now = new \DateTimeImmutable('2026-10-12 12:00:00');
    }

    /**
     * @test
     */
    public function isDisabledWithoutDuration(): void
    {
        $this->inject($this->service, 'duration', '');

        self::assertFalse($this->service->isEnabled());
    }

    /**
     * @test
     */
    public function cookieHasSecureFlagsAndNoPasswordHash(): void
    {
        $account = $this->createAccount('alice', 'bcrypt=>secret-password-hash');

        $cookie = $this->service->createCookie($account, $this->now);

        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        self::assertSame('/', $cookie->getPath());
        self::assertSame(30 * 86400, $cookie->getMaximumAge());
        self::assertStringNotContainsString('secret-password-hash', base64_decode(strtr(substr($cookie->getValue(), 0, -40), '-_', '+/')));
        self::assertStringNotContainsString('alice', base64_decode(strtr(substr($cookie->getValue(), 0, -40), '-_', '+/')));
    }

    /**
     * @test
     */
    public function secureFlagCanBeDisabledBySetting(): void
    {
        $this->inject($this->service, 'secureCookie', false);

        $cookie = $this->service->createCookie($this->createAccount('alice', 'hash'), $this->now);

        self::assertFalse($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
    }

    /**
     * @test
     */
    public function rememberedCookieIsAccepted(): void
    {
        $account = $this->createAccount('alice', 'hash-1');
        $cookie = $this->service->createCookie($account, $this->now);

        self::assertTrue($this->service->isRemembered($account, $this->toCookieParams($cookie), $this->now->modify('+29 days')));
    }

    /**
     * @test
     */
    public function cookieIsIgnoredWhenFeatureIsDisabled(): void
    {
        $account = $this->createAccount('alice', 'hash-1');
        $cookie = $this->service->createCookie($account, $this->now);
        $this->inject($this->service, 'duration', '');

        self::assertFalse($this->service->isRemembered($account, $this->toCookieParams($cookie), $this->now));
    }

    /**
     * @test
     */
    public function expiredCookieIsRejected(): void
    {
        $account = $this->createAccount('alice', 'hash-1');
        $cookie = $this->service->createCookie($account, $this->now);

        self::assertFalse($this->service->isRemembered($account, $this->toCookieParams($cookie), $this->now->modify('+31 days')));
    }

    /**
     * @test
     */
    public function tamperedCookieIsRejected(): void
    {
        $account = $this->createAccount('alice', 'hash-1');
        $cookie = $this->service->createCookie($account, $this->now);
        $value = $cookie->getValue();
        $value[0] = $value[0] === 'a' ? 'b' : 'a';

        self::assertFalse($this->service->isRemembered($account, [$cookie->getName() => $value], $this->now));
        self::assertFalse($this->service->isRemembered($account, [$cookie->getName() => 'garbage'], $this->now));
    }

    /**
     * @test
     */
    public function cookieOfAnotherAccountIsRejected(): void
    {
        $alice = $this->createAccount('alice', 'hash-1');
        $bob = $this->createAccount('bob', 'hash-1');
        $aliceCookie = $this->service->createCookie($alice, $this->now);
        $bobCookie = $this->service->createCookie($bob, $this->now);

        self::assertNotSame($aliceCookie->getName(), $bobCookie->getName());
        // even when sent under bob's cookie name
        self::assertFalse($this->service->isRemembered($bob, [$bobCookie->getName() => $aliceCookie->getValue()], $this->now));
    }

    /**
     * @test
     */
    public function changedCredentialsSourceIsRejected(): void
    {
        $account = $this->createAccount('alice', 'hash-1');
        $cookie = $this->service->createCookie($account, $this->now);

        $account->setCredentialsSource('hash-2');

        self::assertFalse($this->service->isRemembered($account, $this->toCookieParams($cookie), $this->now));
    }

    /**
     * @test
     */
    public function changedRevocationMarkerIsRejected(): void
    {
        $account = $this->createAccount('alice', 'hash-1');
        $cookie = $this->service->createCookie($account, $this->now);

        $this->revocationMarker = '2026-10-13T08:00:00+02:00';

        self::assertFalse($this->service->isRemembered($account, $this->toCookieParams($cookie), $this->now));
    }

    protected function toCookieParams(Cookie $cookie): array
    {
        return [$cookie->getName() => $cookie->getValue()];
    }

    protected function createAccount(string $identifier, string $credentialsSource): Account
    {
        $account = new Account();
        $account->setAccountIdentifier($identifier);
        $account->setAuthenticationProviderName('Networkteam.Neos.FrontendLogin:Frontend');
        $account->setCredentialsSource($credentialsSource);

        return $account;
    }
}
