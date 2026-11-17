<?php
namespace Networkteam\Neos\FrontendLogin\Security\SecondFactor;

/***************************************************************
 *  (c) 2026 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Security\Account;

/**
 * Backend of the second factor (e.g. an email one-time password checked by an external system)
 *
 * Bind your own implementation in Objects.yaml. The default implementation never requires a second factor.
 */
interface SecondFactorProviderInterface
{

    /**
     * Whether the account must pass a second factor after the password check
     */
    public function isRequired(Account $account): bool;

    /**
     * Trigger the delivery of a new code (e.g. ask the backend to send an email)
     */
    public function sendCode(Account $account): void;

    /**
     * Check the code the user entered
     */
    public function verifyCode(Account $account, string $code): bool;

    /**
     * A value that invalidates all remembered browsers of the account when it changes on the backend side
     */
    public function getRememberRevocationMarker(Account $account): string;
}
