<?php
namespace Networkteam\Neos\FrontendLogin;

use Neos\ContentRepository\Domain\Service\PublishingService;
use Neos\Flow\Core\Bootstrap;
use Networkteam\Neos\FrontendLogin\Service\MemberAreaRootNodePathCache;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

class Package extends \Neos\Flow\Package\Package
{

    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(
            PublishingService::class, 'nodePublished',
            MemberAreaRootNodePathCache::class, 'persist'
        );

    }

}