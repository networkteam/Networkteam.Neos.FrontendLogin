<?php
namespace Networkteam\Neos\FrontendLogin\Helper;

/***************************************************************
 *  (c) 2019 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Error\Messages\Error;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\FlashMessageContainer;

class FlashMessageHelper
{
    /**
     * @Flow\Inject
     * @var FlashMessageContainer
     */
    protected $flashMessageContainer;

    /**
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    public function addErrorMessage(string $labelId, int $code, array $labelArguments = []): void
    {
        $message = $this->translator->translateById(sprintf('%s.body', $labelId), $labelArguments,null,null,'Main','Networkteam.Neos.FrontendLogin');
        $title = $this->translator->translateById(sprintf('%s.title', $labelId), [],null,null,'Main','Networkteam.Neos.FrontendLogin');
        $error = new Error($message, $code, [], $title);
        $this->flashMessageContainer->addMessage($error);
    }
}