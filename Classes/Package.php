<?php
namespace Networkteam\Neos\FrontendLogin;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Model\Node;
use Neos\Flow\Core\Bootstrap;
use Neos\ContentRepository\Domain\Service\Context;
use Networkteam\Neos\FrontendLogin\Service\NodeAccessService;

class Package extends \Neos\Flow\Package\Package
{

    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(Node::class, 'nodeUpdated', NodeAccessService::class, 'updateAccessRoles');
        $dispatcher->connect(Node::class, 'nodePathChanged', NodeAccessService::class, 'updateAccessRoles');
    }

}