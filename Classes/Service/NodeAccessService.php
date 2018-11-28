<?php

namespace Networkteam\Neos\FrontendLogin\Service;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class NodeAccessService
{
    const MEMBERAREAROOT_NODETYPE_NAME = 'Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot';

    const FRONTEND_USER_ROLE_NAME = 'Networkteam.Neos.FrontendLogin:FrontendUser';

    public function updateAccessRoles(NodeInterface $node)
    {
        if ($this->isMemberAreaNode($node)) {
            $this->addFrontendUserAccessRole($node);
        }
    }

    protected function isMemberAreaNode(NodeInterface $node): bool
    {
        if ($node->getNodeType()->isOfType('Neos.Neos:Document') === false) {
            return false;
        }

        if ($node->getNodeType()->isOfType(self::MEMBERAREAROOT_NODETYPE_NAME)) {
            return true;
        }

        $q = new FlowQuery([$node]);
        /** @var NodeInterface $memberAreaRootNode */
        $memberAreaRootNodes = $q->parents('[instanceof ' . self::MEMBERAREAROOT_NODETYPE_NAME . ']');

        return count($memberAreaRootNodes) > 0;
    }

    protected function addFrontendUserAccessRole(NodeInterface $node): void
    {
        // Die Prüfung of die Rolle bereits vorhanden is wird innerhalb von \Neos\ContentRepository\Domain\Model\Node::setAccessRoles erledigt
        $accessRoles = $node->getAccessRoles();
        $accessRoles[] = self::FRONTEND_USER_ROLE_NAME;
        $node->setAccessRoles($accessRoles);
    }

}
