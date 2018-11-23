<?php
namespace Networkteam\Neos\FrontendLogin\Service;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;

class MemberAreaRootNodePathCache
{
    public function persist(NodeInterface $node, Workspace $targetWorkspace)
    {
        $foo = 'bar';
    }
}