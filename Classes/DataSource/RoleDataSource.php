<?php
namespace Networkteam\Neos\FrontendLogin\DataSource;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\DataSource\AbstractDataSource;

class RoleDataSource extends AbstractDataSource
{

    /**
     * @var string
     */
    protected static $identifier = 'networkteam-neos-frontendlogin-roles';

    /**
     * @Flow\Inject
     * @var \Networkteam\Neos\FrontendLogin\Service\RoleService
     */
    protected $roleService;

    /**
     * Get data
     * The return value must be JSON serializable data structure.
     *
     * @param NodeInterface $node The node that is currently edited (optional)
     * @param array $arguments Additional arguments (key / value)
     * @return mixed JSON serializable data
     * @api
     */
    public function getData(NodeInterface $node = null, array $arguments = [])
    {
        $memberAreaRoles = $this->roleService->getMemberAreaRoles();
        $roles = [];

        foreach ($memberAreaRoles as $role) {
            $roles[$role->getIdentifier()] = [
                'label' => $role->getName(),
                'group' => $role->getPackageKey(),
                'icon' => 'fas fa-user-tag'
            ];
        }

        // Sort roles by group first and than by label.
        // @see https://neos.readthedocs.io/en/stable/References/PropertyEditorReference.html?highlight=SelectBoxEditor
        uasort($roles, ['self', 'sortRolesByPackageKey']);
        uasort($roles, ['self', 'sortRolesByLabel']);

        return $roles;
    }

    static protected function sortRolesByPackageKey(array $a, array $b): int
    {
        if ($a['group'] == $b['group']) {
            return 0;
        }

        return $a['group'] < $b['group'] ? -1 : 1;
    }

    static protected function sortRolesByLabel(array $a, array $b): int
    {
        if ($a['label'] == $b['label']) {
            return 0;
        }

        return $a['label'] < $b['label'] ? -1 : 1;
    }
}
