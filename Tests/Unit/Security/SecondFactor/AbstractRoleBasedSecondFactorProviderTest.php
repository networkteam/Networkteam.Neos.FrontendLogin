<?php
namespace Networkteam\Neos\FrontendLogin\Tests\Unit\Security\SecondFactor;

use Neos\Flow\Security\Account;
use Neos\Flow\Security\Policy\Role;
use Neos\Flow\Tests\UnitTestCase;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\AbstractRoleBasedSecondFactorProvider;

class AbstractRoleBasedSecondFactorProviderTest extends UnitTestCase
{
    protected AbstractRoleBasedSecondFactorProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new class extends AbstractRoleBasedSecondFactorProvider {
            public function sendCode(Account $account): void
            {
            }

            public function verifyCode(Account $account, string $code): bool
            {
                return false;
            }

            public function getRememberRevocationMarker(Account $account): string
            {
                return '';
            }
        };
    }

    /**
     * @test
     */
    public function isRequiredForAccountWithSecondFactorRequiredRole(): void
    {
        $account = $this->createAccount([
            new Role('Networkteam.Neos.FrontendLogin:FrontendUser'),
            new Role(AbstractRoleBasedSecondFactorProvider::ROLE_SECOND_FACTOR_REQUIRED)
        ]);

        self::assertTrue($this->provider->isRequired($account));
    }

    /**
     * @test
     */
    public function isNotRequiredForAccountWithoutSecondFactorRequiredRole(): void
    {
        $account = $this->createAccount([new Role('Networkteam.Neos.FrontendLogin:FrontendUser')]);

        self::assertFalse($this->provider->isRequired($account));
    }

    /**
     * @test
     */
    public function isNotRequiredForAccountWithoutRoles(): void
    {
        self::assertFalse($this->provider->isRequired($this->createAccount([])));
    }

    /**
     * @param array<Role> $roles
     */
    protected function createAccount(array $roles): Account
    {
        $account = new Account();
        $account->setAccountIdentifier('alice');
        $account->setAuthenticationProviderName('Networkteam.Neos.FrontendLogin:Frontend');
        $account->setRoles($roles);

        return $account;
    }
}
