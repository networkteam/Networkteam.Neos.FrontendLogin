<?php
namespace Networkteam\Neos\FrontendLogin\DataSource;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Security\Policy\PolicyService;
use Neos\Flow\Annotations as Flow;

class RoleDataSource extends \Neos\Neos\Service\DataSource\AbstractDataSource
{

    const MEMBER_AREA_ROLE_NAME = 'Networkteam.Neos.FrontendLogin:MemberArea';

    /**
     * @var string
     */
    protected static $identifier = 'networkteam-neos-frontendlogin-roles';

    /**
     * @Flow\Inject
     * @var PolicyService
     */
    protected $policyService;

    /**
     * Get data
     * The return value must be JSON serializable data structure.
     *
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return mixed JSON serializable data
     * @api
     */
    public function getData(NodeInterface $node = null, array $arguments)
    {
        $roles = [];
        $memberAreaRole = $this->policyService->getRole(self::MEMBER_AREA_ROLE_NAME);

        foreach ($this->policyService->getRoles() as $role) {
            if ($role->hasParentRole($memberAreaRole)) {
                $roles[$role->getIdentifier()] = [
                    'label' => $role->getName(),
                    'group' => $role->getPackageKey(),
                    'icon' => 'fas fa-user-tag'
                ];
            }
        }

        return $roles;
    }
}
