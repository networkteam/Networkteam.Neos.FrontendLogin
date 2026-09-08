<?php
namespace Networkteam\Neos\FrontendLogin;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Security\Policy\PolicyService;
use Networkteam\Neos\FrontendLogin\Security\PolicyConfiguration;

class Package extends \Neos\Flow\Package\Package
{

    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(
            PolicyService::class, 'configurationLoaded',
            PolicyConfiguration::class, 'addFrontendPrivilegesToPolicyConfiguration'
        );
    }

}