<?php
namespace Networkteam\Neos\FrontendLogin\Security;

/***************************************************************
 *  (c) 2019 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\RequestInterface;
use Neos\Flow\Security\RequestPatternInterface;

class NeosRequestPattern implements RequestPatternInterface
{

    /**
     * @var array
     */
    protected $options;

    /**
     * Expects options in the form array('matchFrontend' => TRUE/FALSE)
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;
    }

    /**
     * Matches an ActionRequest against its set pattern rules
     *
     * @param ActionRequest $request The request that should be matched
     * @return boolean true if the pattern matched, false otherwise
     */
    public function matchRequest(ActionRequest $request)
    {
        if (!$request instanceof ActionRequest) {
            return false;
        }
        $shouldMatchFrontend = isset($this->options['matchFrontend']) && $this->options['matchFrontend'] === true;
        $requestPath = $request->getHttpRequest()->getUri()->getPath();
        $requestPathMatchesBackend = substr($requestPath, 0, 5) === '/neos' || strpos($requestPath, '@') !== false || substr($requestPath, 0, 10) === '/sitegeist';
        return $shouldMatchFrontend !== $requestPathMatchesBackend;
    }

}
