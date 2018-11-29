<?php

namespace Networkteam\Neos\FrontendLogin\Service;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class NodeAccessService
{
    const MEMBERAREAROOT_NODETYPE_NAME = 'Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot';

    const DEFAULT_MEMBERAREA_ROLE_NAME = 'Networkteam.Neos.FrontendLogin:FrontendUser';

    protected $processedNodes = [];

    public function updateAccessRoles(NodeInterface $node)
    {
        if ($node->getNodeType()->isOfType('Neos.Neos:Document') === false) {
            return;
        }

        if (in_array($node->getIdentifier(), $this->processedNodes)) {
            return;
        }

        $memberAreaRootNode = $this->getMemberAreaRootNodeFromDocumentNode($node);

        if ($memberAreaRootNode instanceof NodeInterface) {
            $this->addFrontendUserAccessRole($node, $memberAreaRootNode);
        } else {
            $this->removeAllMemberAreaRoles($node);
        }

        $this->processedNodes[] = $node->getIdentifier();
    }

    protected function getMemberAreaRootNodeFromDocumentNode(NodeInterface $node): ?NodeInterface
    {
        if ($node->getNodeType()->isOfType(self::MEMBERAREAROOT_NODETYPE_NAME)) {
            return $node;
        }

        $q = new FlowQuery([$node]);
        /** @var NodeInterface $memberAreaRootNode */
        $memberAreaRootNodes = $q->parents('[instanceof ' . self::MEMBERAREAROOT_NODETYPE_NAME . ']');

        return $memberAreaRootNodes->get(0);
    }

    protected function addFrontendUserAccessRole(NodeInterface $node, NodeInterface $memberAreaRootNode): void
    {
        $accessRoles = $node->getAccessRoles();

        // before adding roles we need to remove all other member area nodes
        $roles = $this->getRolesFromAllMemberAreas($node);
        $keysToRemove = array_keys($accessRoles, $roles);
        if ($keysToRemove) {
            foreach ($keysToRemove as $key) {
                unset($accessRoles[$key]);
            }
        }

        $memberAreaRoles = $memberAreaRootNode->getProperty('roles');
        if (empty($memberAreaRoles)) {
            $memberAreaRoles = [self::DEFAULT_MEMBERAREA_ROLE_NAME];
        }

        if ($memberAreaRoles) {
            foreach ($memberAreaRoles as $roleIdentifier) {
                $accessRoles[] = $roleIdentifier;
            }

            // We do not need to check for existance of frontend user roles to prevent a nodeUpdate signal.
            // This is done within \Neos\ContentRepository\Domain\Model\Node::setAccessRoles
            $node->setAccessRoles(array_unique($accessRoles));
        }
    }

    protected function removeAllMemberAreaRoles(NodeInterface $node): void
    {
        $roles = $this->getRolesFromAllMemberAreas($node);

        $accessRoles = $node->getAccessRoles();
        $keysToRemove = array_keys($accessRoles, $roles);
        if ($keysToRemove) {
            foreach ($keysToRemove as $key) {
                unset($accessRoles[$key]);
            }

            $node->setAccessRoles($accessRoles);
        }
    }

    protected function getRolesFromAllMemberAreas(NodeInterface $node): array
    {
        $q = new FlowQuery([$node->getContext()->getRootNode()]);
        /** @var NodeInterface $memberAreaRootNode */
        $memberAreaRootNodes = $q->find('[instanceof ' . self::MEMBERAREAROOT_NODETYPE_NAME . ']');

        $roles = [];
        foreach ($memberAreaRootNodes as $memberAreaRootNode) {
            $memberAreaRoles = $memberAreaRootNode->getProperty('roles');
            if (is_array($memberAreaRoles)) {
                $roles = array_merge($roles, $memberAreaRoles);
            }
        }

        return array_unique($roles);
    }
}
