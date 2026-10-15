<?php
namespace Networkteam\Neos\FrontendLogin\Security\SecondFactor;

/***************************************************************
 *  (c) 2026 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Security\Account;
use Neos\Flow\Security\Policy\Role;

/**
 * Base class for second factor providers that require a second factor only for accounts with a dedicated role
 *
 * Grant the role "Networkteam.Neos.FrontendLogin:SecondFactorRequired" to an account (e.g. in the authentication
 * provider) to require a second factor. Code delivery and verification stay with the concrete provider.
 */
abstract class AbstractRoleBasedSecondFactorProvider implements SecondFactorProviderInterface
{

    public const ROLE_SECOND_FACTOR_REQUIRED = 'Networkteam.Neos.FrontendLogin:SecondFactorRequired';

    public function isRequired(Account $account): bool
    {
        return $account->hasRole(new Role(self::ROLE_SECOND_FACTOR_REQUIRED));
    }
}
