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

    // FIXME: Neos\ContentRepository\Domain\Service\NodeService does not exists anymore
//    /**
//     * @Flow\Inject
//     * @var NodeService
//     */
//    protected $nodeService;

    #[\Neos\Flow\Annotations\Inject]
    protected \Neos\ContentRepositoryRegistry\ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * Update access roles for node being part of member area (MemberAreaRoot as parent). This method is triggerd
     * when a node is updated (edit, move)
     * @param \Neos\ContentRepository\Core\Projection\ContentGraph\Node $node
     */
    public function updateAccessRoles(\Neos\ContentRepository\Core\Projection\ContentGraph\Node $node)
    {
        $contentRepository = $this->contentRepositoryRegistry->get($node->contentRepositoryId);
        $isDocumentNode = $contentRepository->getNodeTypeManager()->getNodeType($node->nodeTypeName)->isOfType('Neos.Neos:Document');
        // TODO 9.0 migration: Check if you could change your code to work with the NodeAggregateId value object instead.
        $isProcessedNode = in_array($node->aggregateId->value, $this->processedNodes);

        if (!$isDocumentNode || $isProcessedNode) {
            return;
        }

        $memberAreaRootNode = $this->getMemberAreaRootNodeFromDocumentNode($node);

        // do not handle MemberAreaRoot nodes
        if ($node === $memberAreaRootNode) {
            return;
        }

        if ($memberAreaRootNode instanceof \Neos\ContentRepository\Core\Projection\ContentGraph\Node) {
            $accessRoles = $memberAreaRootNode->getProperty('accessRoles') ?? [];
            $this->setMemberAreaAccessRoles($node, $accessRoles);
        } else {
            $this->removeAllMemberAreaRoles($node);
        }

        // TODO 9.0 migration: Check if you could change your code to work with the NodeAggregateId value object instead.
        $this->processedNodes[] = $node->aggregateId->value;
    }

    /**
     * Set accessRoles properties (accessRoles, _accessRoles) on all children of MemberAreaRoot node and
     * MemberAreaRoot node itself.
     *
     * @param \Neos\ContentRepository\Core\Projection\ContentGraph\Node $node
     * @param $propertyName
     * @param $oldValue
     * @param $value
     * @throws \Neos\Eel\Exception
     */
    public function setAccessRolesOnMemberAreaRootAndChildren(\Neos\ContentRepository\Core\Projection\ContentGraph\Node $node, $propertyName, $oldValue, $value): void
    {
        $contentRepository = $this->contentRepositoryRegistry->get($node->contentRepositoryId);
        $isMemberAreaRootNode = $contentRepository->getNodeTypeManager()->getNodeType($node->nodeTypeName)->isOfType(self::MEMBERAREAROOT_NODETYPE_NAME) && $contentRepository->getNodeTypeManager()->getNodeType($node->nodeTypeName)->isOfType('Neos.Neos:Document');
        $isAccessRolesProperty = $propertyName === 'accessRoles';

        if ($isMemberAreaRootNode && $isAccessRolesProperty) {
            // update internal property "_accessRoles" of MemberAreaRoot
            $accessRoles = $node->getProperty('accessRoles') ?? [];
            $this->setMemberAreaAccessRoles($node, $accessRoles);

            // update accessRoles property of all child nodes
            $q = new FlowQuery([$node]);
            $children = $q->find(sprintf('[instanceof %s]', NodeAccessService::MIXINS_ACCESSROLES_NODETYPE_NAME));

            /** @var NodeInterface $childNode */
            // TODO 9.0 migration: !! Node::setProperty() is not supported by the new CR. Use the "SetNodeProperties" command to change property values.
            foreach ($children as $childNode) {
                // this leads to an node update signal which triggers the execution of self::updateAccessRoles
                // TODO 9.0 migration: !! Node::setProperty() is not supported by the new CR. Use the "SetNodeProperties" command to change property values.
                $childNode->setProperty($propertyName, $value);
            }
        }
    }

    protected function getMemberAreaRootNodeFromDocumentNode(\Neos\ContentRepository\Core\Projection\ContentGraph\Node $node): ?\Neos\ContentRepository\Core\Projection\ContentGraph\Node
    {
        $contentRepository = $this->contentRepositoryRegistry->get($node->contentRepositoryId);
        if ($contentRepository->getNodeTypeManager()->getNodeType($node->nodeTypeName)->isOfType(self::MEMBERAREAROOT_NODETYPE_NAME)) {
            return $node;
        }

        $q = new FlowQuery([$node]);
        /** @var \Neos\ContentRepository\Core\Projection\ContentGraph\Node $memberAreaRootNode */
        $memberAreaRootNodes = $q->parents('[instanceof ' . self::MEMBERAREAROOT_NODETYPE_NAME . ']');

        return $memberAreaRootNodes->get(0);
    }

    protected function setMemberAreaAccessRoles(\Neos\ContentRepository\Core\Projection\ContentGraph\Node $node, array $accessRoles): void
    {
        // before adding roles we need to remove all other member area nodes
        $defaultAccessRoles = $this->roleService->getAccessRolesForNodeWithoutMemberAreaRoles($node);
        $accessRoles = array_unique(array_merge($defaultAccessRoles, $accessRoles));

        // We do not need to check for existance of frontend user roles to prevent a nodeUpdate signal.
        // This is done within \Neos\ContentRepository\Domain\Model\Node::setAccessRoles
        // TODO 9.0 migration: !! Node::setAccessRoles() is not supported by the new CR.
        $node->setAccessRoles($accessRoles);
        // TODO 9.0 migration: !! Node::setProperty() is not supported by the new CR. Use the "SetNodeProperties" command to change property values.
        $node->setProperty('accessRoles', $accessRoles);
    }

    protected function removeAllMemberAreaRoles(\Neos\ContentRepository\Core\Projection\ContentGraph\Node $node): void
    {
        $defaultAccessRoles = $this->roleService->getAccessRolesForNodeWithoutMemberAreaRoles($node);
        // TODO 9.0 migration: !! Node::setAccessRoles() is not supported by the new CR.
        $node->setAccessRoles(array_unique($defaultAccessRoles));
        // TODO 9.0 migration: !! Node::setProperty() is not supported by the new CR. Use the "SetNodeProperties" command to change property values.
        $node->setProperty('accessRoles', $defaultAccessRoles);
    }
}
