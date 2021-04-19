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

    /**
     * Update access roles for node being part of member area (MemberAreaRoot as parent). This method is triggerd
     * when a node is updated (edit, move)
     * @param NodeInterface $node
     */
    public function updateAccessRoles(NodeInterface $node)
    {
        $isDocumentNode = $node->getNodeType()->isOfType('Neos.Neos:Document');
        $isProcessedNode = in_array($node->getIdentifier(), $this->processedNodes);

        if (!$isDocumentNode || $isProcessedNode) {
            return;
        }

        $memberAreaRootNode = $this->getMemberAreaRootNodeFromDocumentNode($node);

        // do not handle MemberAreaRoot nodes
        if ($node === $memberAreaRootNode) {
            return;
        }

        if ($memberAreaRootNode instanceof NodeInterface) {
            $accessRoles = $memberAreaRootNode->getProperty('accessRoles') ?? [];
            $this->setMemberAreaAccessRoles($node, $accessRoles);
        } else {
            $this->removeAllMemberAreaRoles($node);
        }

        $this->processedNodes[] = $node->getIdentifier();
    }

    /**
     * Set accessRoles properties (accessRoles, _accessRoles) on all children of MemberAreaRoot node and
     * MemberAreaRoot node itself.
     *
     * @param NodeInterface $node
     * @param $propertyName
     * @param $oldValue
     * @param $value
     * @throws \Neos\Eel\Exception
     */
    public function setAccessRolesOnMemberAreaRootAndChildren(NodeInterface $node, $propertyName, $oldValue, $value): void
    {
        $isMemberAreaRootNode = $node->getNodeType()->isOfType(self::MEMBERAREAROOT_NODETYPE_NAME) && $node->getNodeType()->isOfType('Neos.Neos:Document');
        $isAccessRolesProperty = $propertyName === 'accessRoles';

        if ($isMemberAreaRootNode && $isAccessRolesProperty) {
            // update internal property "_accessRoles" of MemberAreaRoot
            $accessRoles = $node->getProperty('accessRoles') ?? [];
            $this->setMemberAreaAccessRoles($node, $accessRoles);

            // update accessRoles property of all child nodes
            $q = new FlowQuery([$node]);
            $children = $q->children(sprintf('[instanceof %s]', NodeAccessService::MIXINS_ACCESSROLES_NODETYPE_NAME));

            /** @var NodeInterface $childNode */
            foreach ($children as $childNode) {
                // this leads to an node update signal which triggers the execution of self::updateAccessRoles
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

    protected function setMemberAreaAccessRoles(NodeInterface $node, array $accessRoles): void
    {
        // before adding roles we need to remove all other member area nodes
        $defaultAccessRoles = $this->roleService->getAccessRolesForNodeWithoutMemberAreaRoles($node);
        $accessRoles = array_unique(array_merge($defaultAccessRoles, $accessRoles));

        // We do not need to check for existance of frontend user roles to prevent a nodeUpdate signal.
        // This is done within \Neos\ContentRepository\Domain\Model\Node::setAccessRoles
        $node->setAccessRoles($accessRoles);
        $node->setProperty('accessRoles', $accessRoles);
    }

    protected function removeAllMemberAreaRoles(NodeInterface $node): void
    {
        $defaultAccessRoles = $this->roleService->getAccessRolesForNodeWithoutMemberAreaRoles($node);
        $node->setAccessRoles(array_unique($defaultAccessRoles));
        $node->setProperty('accessRoles', $defaultAccessRoles);
    }
}
