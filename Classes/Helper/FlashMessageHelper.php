<?php
namespace Networkteam\Neos\FrontendLogin\Helper;

/***************************************************************
 *  (c) 2019 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Error\Messages\Error;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\EelHelper\TranslationHelper;
use Neos\Flow\Mvc\FlashMessage\FlashMessageContainer;

class FlashMessageHelper
{
    /**
     * @Flow\Inject
     * @var FlashMessageContainer
     */
    protected $flashMessageContainer;

    public function addErrorMessage(string $labelId, int $code, array $labelArguments = []): void
    {
        $message = $this->getTranslation(sprintf('%s.body', $labelId), $labelArguments);
        $title = $this->getTranslation(sprintf('%s.title', $labelId), $labelArguments);
        $error = new Error($message, $code, [], $title);

        $this->flashMessageContainer->addMessage($error);
    }

    protected function getTranslation(string $id, array $arguments = []): string
    {
        $translationHelper = new TranslationHelper();
        return $translationHelper->translate(
            $id,
            null,
            $arguments,
            'Main',
            'Networkteam.Neos.FrontendLogin'
        );
    }
}
