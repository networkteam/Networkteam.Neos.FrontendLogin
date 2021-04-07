<?php

namespace Networkteam\Neos\FrontendLogin\Service;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\NodeService;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class NodeAccessService
{
    const MEMBERAREAROOT_NODETYPE_NAME = 'Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot';

    const DEFAULT_MEMBERAREA_ROLE_NAME = 'Networkteam.Neos.FrontendLogin:FrontendUser';

    const MIXINS_ACCESSROLES_NODETYPE_NAME = 'Networkteam.Neos.FrontendLogin:Mixins.AccessRoles';

    protected $processedNodes = [];

    /**
     * @Flow\Inject
     * @var RoleService
     */
    protected $roleService;

    /**
     * @Flow\Inject
     * @var NodeService
     */
    protected $nodeService;

    public function updateAccessRoles(NodeInterface $node)
    {
        $isDocumentNode = $node->getNodeType()->isOfType('Neos.Neos:Document');
        $isProcessedNode = in_array($node->getIdentifier(), $this->processedNodes);

        if (!$isDocumentNode || $isProcessedNode) {
            return;
        }

        $memberAreaRootNode = $this->getMemberAreaRootNodeFromDocumentNode($node);

        // do not handle member area root nodes
        if ($memberAreaRootNode !== $node) {
            if ($memberAreaRootNode instanceof NodeInterface) {
                $this->addMemberAreaAccessRoles($node, $memberAreaRootNode);
            } else {
                $this->removeAllMemberAreaRoles($node);
            }

            $this->processedNodes[] = $node->getIdentifier();
        }
    }

    public function updateChildrenAccessRolesProperty(NodeInterface $node, $propertyName, $oldValue, $value): void
    {
        $isMemberAreaRootNode = $node->getNodeType()->isOfType(self::MEMBERAREAROOT_NODETYPE_NAME);
        $isAccessRolesProperty = $propertyName === 'accessRoles';

        if ($isMemberAreaRootNode && $isAccessRolesProperty) {
            // update internal property "_accessRoles"
            if (is_array($value)) {
                $this->addMemberAreaAccessRoles($node, $node);
            }

            // update access role property of all child nodes
            $q = new FlowQuery([$node]);
            $children = $q->children(sprintf('[instanceof %s]', NodeAccessService::MIXINS_ACCESSROLES_NODETYPE_NAME));

            /** @var NodeInterface $childNode */
            foreach ($children as $childNode) {
                // this leads to an node update signal and execution of self::updateAccessRoles
                $childNode->setProperty($propertyName, $value);
            }
        }
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

    protected function addMemberAreaAccessRoles(NodeInterface $node, NodeInterface $memberAreaRootNode): void
    {
        // before adding roles we need to remove all other member area nodes
        $accessRoles = $this->roleService->getAccessRolesForNodeWithoutMemberAreaRoles($node);

        if (is_array($accessRoles)) {
            foreach ($memberAreaRootNode->getProperty('accessRoles') as $roleIdentifier) {
                $accessRoles[] = $roleIdentifier;
            }

            // We do not need to check for existance of frontend user roles to prevent a nodeUpdate signal.
            // This is done within \Neos\ContentRepository\Domain\Model\Node::setAccessRoles
            $node->setAccessRoles(array_unique($accessRoles));
        }
    }

    protected function removeAllMemberAreaRoles(NodeInterface $node): bool
    {
        $accessRoles = $this->roleService->getAccessRolesForNodeWithoutMemberAreaRoles($node);

        if (is_array($accessRoles)) {
            $node->setAccessRoles(array_unique($accessRoles));
            return true;
        } else {
            return false;
        }
    }
}
