<?php
namespace Networkteam\Neos\FrontendLogin\Tests\Unit\Security\SecondFactor;

use Neos\Flow\Security\Account;
use Neos\Flow\Session\SessionManagerInterface;
use Neos\Flow\Session\TransientSession;
use Neos\Flow\Tests\UnitTestCase;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorSessionStorage;

class SecondFactorSessionStorageTest extends UnitTestCase
{
    protected SecondFactorSessionStorage $storage;

    protected TransientSession $session;

    protected function setUp(): void
    {
        $this->session = new TransientSession();
        $sessionManager = $this->createMock(SessionManagerInterface::class);
        $sessionManager->method('getCurrentSession')->willReturn($this->session);

        $this->storage = new SecondFactorSessionStorage();
        $this->inject($this->storage, 'sessionManager', $sessionManager);
    }

    /**
     * @test
     */
    public function statusIsNullWithoutStartedSession(): void
    {
        self::assertNull($this->storage->getStatus($this->createAccount('alice')));
        self::assertFalse($this->storage->isPending($this->createAccount('alice')));
    }

    /**
     * @test
     */
    public function startChallengeSetsPendingStatusAndRedirectTarget(): void
    {
        $account = $this->createAccount('alice');

        $this->storage->startChallenge($account, '/member-area');

        self::assertTrue($this->session->isStarted());
        self::assertSame(SecondFactorSessionStorage::STATUS_NEEDED, $this->storage->getStatus($account));
        self::assertTrue($this->storage->isPending($account));
        self::assertSame('/member-area', $this->storage->getRedirectTarget($account));
    }

    /**
     * @test
     */
    public function markAuthenticatedEndsPendingState(): void
    {
        $account = $this->createAccount('alice');
        $this->storage->startChallenge($account, '/member-area');

        $this->storage->markAuthenticated($account);

        self::assertSame(SecondFactorSessionStorage::STATUS_AUTHENTICATED, $this->storage->getStatus($account));
        self::assertFalse($this->storage->isPending($account));
        self::assertNull($this->storage->getRedirectTarget($account));
    }

    /**
     * @test
     */
    public function stateOfAnotherAccountIsIgnored(): void
    {
        $this->storage->markAuthenticated($this->createAccount('alice'));

        self::assertNull($this->storage->getStatus($this->createAccount('bob')));
    }

    /**
     * @test
     */
    public function resetClearsState(): void
    {
        $account = $this->createAccount('alice');
        $this->storage->startChallenge($account, '/member-area');

        $this->storage->reset();

        self::assertNull($this->storage->getStatus($account));
    }

    protected function createAccount(string $identifier): Account
    {
        $account = new Account();
        $account->setAccountIdentifier($identifier);
        $account->setAuthenticationProviderName('Networkteam.Neos.FrontendLogin:Frontend');

        return $account;
    }
}
