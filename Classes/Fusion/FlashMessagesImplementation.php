<?php
namespace Networkteam\Neos\FrontendLogin\Fusion;

/***************************************************************
 *  (c) 2019 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\FlashMessage\FlashMessageService;
use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Psr\Http\Message\ServerRequestInterface;

class FlashMessagesImplementation extends AbstractFusionObject
{

    /**
     * @Flow\Inject
     * @var FlashMessageService
     */
    protected $flashMessageService;

    /**
     * @return array
     */
    public function evaluate()
    {
        $severity = $this->getSeverity();
        $actionRequest = ActionRequest::fromHttpRequest($this->getHttpRequest());
        $flashMessageContainer = $this->flashMessageService->getFlashMessageContainerForRequest($actionRequest);

        if ($this->getHttpRequest()->getMethod() === "GET") {
            return $flashMessageContainer->getMessagesAndFlush($severity);
        } else {
            return $flashMessageContainer->getMessages($severity);
        }
    }

    public function getSeverity(): ?string
    {
        return $this->fusionValue('severity');
    }

    public function getHttpRequest(): ServerRequestInterface
    {
        return $this->fusionValue('httpRequest');
    }
}
