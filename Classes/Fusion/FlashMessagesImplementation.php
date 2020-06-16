<?php
namespace Networkteam\Neos\FrontendLogin\Fusion;

/***************************************************************
 *  (c) 2019 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\FlashMessage\FlashMessageContainer;
use Neos\Fusion\FusionObjects\AbstractFusionObject;

class FlashMessagesImplementation extends AbstractFusionObject
{

    /**
     * @Flow\Inject
     * @var FlashMessageContainer
     */
    protected $flashMessageContainer;

    /**
     * @return array
     *
     */
    public function evaluate()
    {
        $severity = $this->getSeverity();
        $flashMessages = $this->flashMessageContainer->getMessagesAndFlush($severity);

        return $flashMessages;
    }

    public function getSeverity(): ?string
    {
        return $this->fusionValue('severity');
    }
}
