<?php

namespace Networkteam\Neos\FrontendLogin\Service;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Cache\Frontend\VariableFrontend;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\Neos\Domain\Service\ContentContext;

class MemberAreaRootNodePathCache
{
    const MEMBERAREAROOT_NODETYPE_NAME = 'Networkteam.Neos.FrontendLogin:Mixins.MembersAreaRoot';
    const CACHE_IDENTIFIER = 'nodePaths';
    /**
     * @Flow\Inject
     * @var VariableFrontend
     */
    protected $cache;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;


    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

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

            if ($this->cache->has(self::CACHE_IDENTIFIER)) {
                $nodePaths = $this->cache->get(self::CACHE_IDENTIFIER);
                $nodePaths[$node->getPath()] = $node->getIdentifier();
            } else {
                $nodePaths = [$node->getPath() => $node->getIdentifier()];
            }

            $this->cache->set(self::CACHE_IDENTIFIER, $nodePaths);
        }
    }

    public function get()
    {
        if ($this->cache->has(self::CACHE_IDENTIFIER)) {
            return $this->cache->get(self::CACHE_IDENTIFIER);
        } else {
            //TODO: warmup cache. get all nodes of type self::MEMBERAREAROOT_NODETYPE_NAME
        }

        /** @var ContentContext $contentContext */
        $contentContext = $this->contextFactory->create([
            'workspaceName' => 'live',
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true
        ]);

    }

}
