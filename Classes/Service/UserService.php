<?php
namespace Networkteam\Neos\FrontendLogin\Service;

/***************************************************************
 *  (c) 2021 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryInterface;
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
    public function getUsers(string $sortBy = 'accounts.accountIdentifier', string $sortDirection = QueryInterface::ORDER_ASCENDING, string $authenticationProviderName = null): QueryResultInterface
    {
        return $this->userRepository->findByAuthenticationProviderName(
            $authenticationProviderName ?: $this->defaultAuthenticationProviderName,
            $sortBy,
            $sortDirection
        );
    }

    /**
     * @param string $searchTerm
     * @param string $sortBy
     * @param string $sortDirection
     * @return QueryResultInterface
     */
    public function searchUsers(string $searchTerm, string $sortBy, string $sortDirection): QueryResultInterface
    {
        return $this->userRepository->findBySearchTerm($searchTerm, $sortBy, $sortDirection);
    }

    /**
     * Adds a user whose User object has been created elsewhere. A workspace is NOT created.
     *
     * This method basically "creates" a user like createUser() would, except that it does not create the User
     * object itself. If you need to create the User object elsewhere, for example in your ActionController, make sure
     * to call this method for registering the new user instead of adding it to the PartyRepository manually.
     *
     * @param string $username The username of the user to be created.
     * @param string $password Password of the user to be created
     * @param User $user The pre-built user object to start with
     * @param array $roleIdentifiers A list of role identifiers to assign
     * @param string $authenticationProviderName Name of the authentication provider to use. Example: "Neos.Neos:Backend"
     * @return User The same user object
     * @api
     */
    public function addUser($username, $password, User $user, array $roleIdentifiers = null, $authenticationProviderName = null)
    {
        if ($roleIdentifiers === null) {
            $roleIdentifiers = ['Networkteam.Neos.FrontendLogin:FrontendUser'];
        }
        $roleIdentifiers = $this->normalizeRoleIdentifiers($roleIdentifiers);
        $account = $this->accountFactory->createAccountWithPassword(
            $username,
            $password,
            $roleIdentifiers,
            $authenticationProviderName ?: $this->defaultAuthenticationProviderName
        );
        $this->partyService->assignAccountToParty($account, $user);

        $this->partyRepository->add($user);
        $this->accountRepository->add($account);

        $this->emitFrontendUserCreated($user);

        return $user;
    }


    /**
     * Signals that a new frontend user, including a new account has been created.
     *
     * @param User $user The created user
     * @return void
     * @Flow\Signal
     * @api
     */
    public function emitFrontendUserCreated(User $user)
    {
    }
}

