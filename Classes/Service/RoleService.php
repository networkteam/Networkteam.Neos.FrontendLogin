<?php
namespace Networkteam\Neos\FrontendLogin\Service;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Exception\NoSuchRoleException;
use Neos\Flow\Security\Policy\Role;

class RoleService
{
    const MEMBER_AREA_ROLE_NAME = 'Networkteam.Neos.FrontendLogin:MemberArea';

    /**
     * @Flow\Inject
     * @var \Neos\Flow\Security\Policy\PolicyService
     */
    protected $policyService;

    /**
     * @return Role[]
     * @throws NoSuchRoleException
     */
    public function getMemberAreaRoles(): array
    {
        $memberAreaAbstractRole = $this->policyService->getRole(self::MEMBER_AREA_ROLE_NAME);
        $roles = $this->policyService->getRoles();

        $memberAreaRoles = array_filter($roles, function(Role $role) use ($memberAreaAbstractRole) {
            return $role->hasParentRole($memberAreaAbstractRole) && !$role->isAbstract();
        });

        return $memberAreaRoles;
    }

    public function isMemberAreaRole(string $roleIdentifier): bool
    {
        $isMemberAreaRole = false;

        try {
            $memberAreaRoles = $this->getMemberAreaRoles();
            foreach ($memberAreaRoles as $role) {
                if ($role->getIdentifier() === $roleIdentifier) {
                    $isMemberAreaRole = true;
                    break;
                }
            }
        } catch (\Exception $e) {

        }

        return $isMemberAreaRole;
    }

    /**
     * Remove all MemberArea roles from given node and return remaining roles
     *
     * @param NodeInterface $node
     * @return array|null Returns an array of remaining roles and null if an error occurred
     */
    public function getAccessRolesForNodeWithoutMemberAreaRoles(NodeInterface $node): ?array
    {
        $accessRoles = [];
        foreach ($node->getAccessRoles() as $roleIdentfier) {
            if (!$this->isMemberAreaRole($roleIdentfier)) {
                $accessRoles[] = $roleIdentfier;
            }
        }

        return $accessRoles;
    }
}
