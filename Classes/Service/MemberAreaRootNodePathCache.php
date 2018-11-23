<?php
namespace Networkteam\Neos\FrontendLogin\Service;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Cache\Frontend\StringFrontend;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;

class MemberAreaRootNodePathCache
{
    const MEMBERAREAROOT_NODETYPE_NAME = 'Networkteam.Neos.FrontendLogin:Mixins.MembersAreaRoot';

    /**
     * @Flow\Inject
     * @var StringFrontend
     */
    protected $cache;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @param NodeInterface $node
     * @param string $nodeTypeName
     * @return boolean
     */
    protected function isNodeOfType(NodeInterface $node, $nodeTypeName)
    {
        if ($node->getNodeType()->getName() === $nodeTypeName) {
            return true;
        }
        $subNodeTypes = $this->nodeTypeManager->getSubNodeTypes($nodeTypeName);
        return isset($subNodeTypes[$node->getNodeType()->getName()]);
    }

    public function persist(NodeInterface $node, Workspace $targetWorkspace)
    {
        if ($this->isNodeOfType($node, self::MEMBERAREAROOT_NODETYPE_NAME)) {
            $this->cache->set($node->getIdentifier(), $node->getPath());
        }
    }
}
