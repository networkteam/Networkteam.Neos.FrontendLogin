<?php
namespace Networkteam\Neos\FrontendLogin\Service;

/***************************************************************
 *  (c) 2026 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Account;
use Neos\Neos\Domain\Service\ContentContext;

/**
 * Finds the member area and portal root nodes of an account
 *
 * @Flow\Scope("singleton")
 */
class MemberAreaService
{
    const PORTALROOT_NODETYPE_NAME = 'Networkteam.Neos.FrontendLogin:Mixins.PortalRoot';

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\InjectConfiguration(path="roleToMemberAreaMapping")
     * @var array
     */
    protected $roleToMemberAreaMapping;

    public function getMemberAreaNodeTypeForAccount(Account $account): ?string
    {
        $memberAreaRootNodeType = null;
        /** @var \Neos\Flow\Security\Policy\Role $role */
        foreach ($account->getRoles() as $role) {
            if (!empty($this->roleToMemberAreaMapping[$role->getIdentifier()])) {
                $memberAreaRootNodeType = $this->roleToMemberAreaMapping[$role->getIdentifier()];
                break;
            }
        }
        return $memberAreaRootNodeType;
    }

    /**
     * Find the MemberAreaRoot node of the current site the account has access to
     */
    public function findMemberAreaRootNodeForAccount(Account $account, ContentContext $contentContext): ?NodeInterface
    {
        $memberAreaRootNodeType = $this->getMemberAreaNodeTypeForAccount($account);
        if ($memberAreaRootNodeType === null || $contentContext->getCurrentSiteNode() === null) {
            return null;
        }

        $q = new FlowQuery([$contentContext->getCurrentSiteNode()]);
        return $q->find(sprintf('[instanceof %s]', $memberAreaRootNodeType))->get(0);
    }

    /**
     * Find the portal root of the account: the closest node with the PortalRoot mixin, starting at the account's
     * member area root. Falls back to the site node.
     *
     * The node is loaded from the live workspace in the default dimensions.
     */
    public function getPortalRootNode(Account $account): NodeInterface
    {
        $contentContext = $this->createLiveContext();

        $node = $this->findMemberAreaRootNodeForAccount($account, $contentContext);
        while ($node instanceof NodeInterface) {
            if ($node->getNodeType()->isOfType(self::PORTALROOT_NODETYPE_NAME)) {
                return $node;
            }
            $node = $node->getParent();
        }

        $siteNode = $contentContext->getCurrentSiteNode();
        if (!$siteNode instanceof NodeInterface) {
            throw new \RuntimeException('Could not find a site node for the current request.', 1792047601);
        }

        return $siteNode;
    }

    protected function createLiveContext(): ContentContext
    {
        /** @var ContentContext $contentContext */
        $contentContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'invisibleContentShown' => false,
            'inaccessibleContentShown' => false
        ]);

        return $contentContext;
    }
}
