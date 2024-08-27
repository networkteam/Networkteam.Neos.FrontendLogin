<?php
namespace Networkteam\Neos\FrontendLogin;

/***************************************************************
 *  (c) 2021 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\QueryInterface;
use Neos\Flow\Persistence\QueryResultInterface;
use Neos\Neos\Domain\Model\User;

/**
 * @Flow\Scope("singleton")
 */
class UserRepository extends \Neos\Neos\Domain\Repository\UserRepository
{

    /**
     * @var string
     */
    const ENTITY_CLASSNAME = User::class;

    /**
     * @param string $authenticationProviderName
     * @param string $sortBy
     * @param string $sortDirection
     * @return QueryResultInterface
     */
    public function findByAuthenticationProviderName(string $authenticationProviderName, string $sortBy = 'accounts.accountIdentifier', string $sortDirection = QueryInterface::ORDER_ASCENDING): QueryResultInterface
    {
        try {
            $query = $this->createQuery();
            $query->matching(
                $query->equals('accounts.authenticationProviderName', $authenticationProviderName),
            );
            return $query->setOrderings([$sortBy => $sortDirection])->execute();
        } catch (\Neos\Flow\Persistence\Exception\InvalidQueryException $e) {
            throw new \RuntimeException($e->getMessage(), 1621946651, $e);
        }
    }

    /**
     * @param string $searchTerm
     * @param string $authenticationProviderName
     * @return QueryResultInterface
     */
    public function findBySearchTermAndAuthenticationProviderName(string $searchTerm, string $authenticationProviderName): QueryResultInterface
    {
        try {
            $query = $this->createQuery();
            $query->matching(
                $query->logicalAnd(
                    $query->equals('accounts.authenticationProviderName', $authenticationProviderName),
                    $query->logicalOr(
                        $query->like('accounts.accountIdentifier', '%'.$searchTerm.'%'),
                        $query->like('name.fullName', '%'.$searchTerm.'%')
                    )
                )
            );
            return $query->setOrderings(['accounts.accountIdentifier' => QueryInterface::ORDER_ASCENDING])->execute();
        } catch (\Neos\Flow\Persistence\Exception\InvalidQueryException $e) {
            throw new \RuntimeException($e->getMessage(), 1621946658, $e);
        }
    }

}

