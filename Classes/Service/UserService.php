<?php
namespace Networkteam\Neos\FrontendLogin\Service;

/***************************************************************
 *  (c) 2021 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Flow\Security\AccountFactory;
use Neos\Neos\Domain\Model\User;
use Neos\Party\Domain\Service\PartyService;
use Networkteam\Neos\FrontendLogin\UserRepository;

/**
 * @Flow\Scope("singleton")
 */
class UserService extends \Neos\Neos\Domain\Service\UserService
{

    protected $defaultAuthenticationProviderName = 'Networkteam.Neos.FrontendLogin:Frontend';

    /**
     * @Flow\Inject
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @param string $authenticationProviderName
     * @return QueryResultInterface
     */
    public function getUsers(string $authenticationProviderName = null): QueryResultInterface
    {
        return $this->userRepository->findByAuthenticationProviderName($authenticationProviderName ?: $this->defaultAuthenticationProviderName);
    }

    /**
     * @param string $searchTerm
     * @param string $authenticationProviderName
     * @return QueryResultInterface
     */
    public function searchUsers(string $searchTerm, string $authenticationProviderName = null): QueryResultInterface
    {
        return $this->userRepository->findBySearchTermAndAuthenticationProviderName(
            $searchTerm,
            $authenticationProviderName ?: $this->defaultAuthenticationProviderName
        );
    }

}

