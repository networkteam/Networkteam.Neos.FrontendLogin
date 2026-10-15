<?php
namespace Networkteam\Neos\FrontendLogin\Security\SecondFactor;

/***************************************************************
 *  (c) 2026 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Account;

/**
 * Default second factor provider: no account requires a second factor
 *
 * @Flow\Scope("singleton")
 */
class NoSecondFactorProvider implements SecondFactorProviderInterface
{

    public function isRequired(Account $account): bool
    {
        return false;
    }

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
}
