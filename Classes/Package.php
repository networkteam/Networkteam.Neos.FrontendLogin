<?php
namespace Networkteam\Neos\FrontendLogin;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Model\Node;
use Neos\Flow\Core\Bootstrap;
use Networkteam\Neos\FrontendLogin\Service\NodeAccessService;

class Package extends \Neos\Flow\Package\Package
{

    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        // TODO 9.0 migration: The signal "nodeUpdated" on "Node" has been removed. Please check https://docs.neos.io/api/upgrade-instructions/9/signals-and-slots for further information, how to replace a signal.
        $dispatcher->connect(\Neos\ContentRepository\Core\Projection\ContentGraph\Node::class, 'nodeUpdated', NodeAccessService::class, 'updateAccessRoles');
        // TODO 9.0 migration: The signal "nodePathChanged" on "Node" has been removed. Please check https://docs.neos.io/api/upgrade-instructions/9/signals-and-slots for further information, how to replace a signal.
        $dispatcher->connect(\Neos\ContentRepository\Core\Projection\ContentGraph\Node::class, 'nodePathChanged', NodeAccessService::class, 'updateAccessRoles');
        // TODO 9.0 migration: The signal "nodePropertyChanged" on "Node" has been removed. Please check https://docs.neos.io/api/upgrade-instructions/9/signals-and-slots for further information, how to replace a signal.
        $dispatcher->connect(\Neos\ContentRepository\Core\Projection\ContentGraph\Node::class, 'nodePropertyChanged', NodeAccessService::class, 'setAccessRolesOnMemberAreaRootAndChildren');
    }

}