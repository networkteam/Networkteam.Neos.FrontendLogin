<?php
namespace Networkteam\Neos\FrontendLogin\Eel;


/***************************************************************
 *  (c) 2020 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Service;

class LocaleHelper implements \Neos\Eel\ProtectedContextAwareInterface
{

    /**
     * @Flow\Inject
     * @var Service
     */
    protected $i18nService;

    public function current(): string
    {
        return (string)$this->i18nService->getConfiguration()->getCurrentLocale();
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
