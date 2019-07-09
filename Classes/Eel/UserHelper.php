<?php
namespace Networkteam\Neos\FrontendLogin\Eel;

/***************************************************************
 *  (c) 2019 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Context;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Service\UserService;


class UserHelper implements ProtectedContextAwareInterface
{

    /**
     * @Flow\InjectConfiguration(path="authenticationProviderName")
     * @var string
     */
    protected $authenticationProviderName;

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @Flow\Inject
     * @var Context
     */
    protected $securityContext;

    public function getCurrentUser(): ?User
    {
        if ($this->securityContext->canBeInitialized() === true) {
            $account = $this->securityContext->getAccount();
            if ($account !== null) {
                return $this->userService->getUser($account->getAccountIdentifier(), $this->authenticationProviderName);
            }
        }

        return null;
    }

    /**
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}