<?php
namespace Networkteam\Neos\FrontendLogin\Security\SecondFactor;

/***************************************************************
 *  (c) 2026 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Security\Account;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Starts the second factor challenge and builds the URI of the code form
 *
 * @Flow\Scope("singleton")
 */
class SecondFactorService
{

    /**
     * @Flow\Inject
     * @var SecondFactorProviderInterface
     */
    protected $secondFactorProvider;

    /**
     * @Flow\Inject
     * @var SecondFactorSessionStorage
     */
    protected $sessionStorage;

    /**
     * @Flow\Inject
     * @var RememberedBrowserService
     */
    protected $rememberedBrowserService;

    public function isRequired(Account $account): bool
    {
        return $this->secondFactorProvider->isRequired($account);
    }

    /**
     * Start the challenge for an account that passed the password check
     *
     * @return bool true if the code is needed, false if the browser is remembered and the code is skipped
     */
    public function startChallenge(Account $account, string $redirectTarget, ServerRequestInterface $httpRequest): bool
    {
        if ($this->rememberedBrowserService->isRemembered($account, $httpRequest->getCookieParams())) {
            $this->sessionStorage->markAuthenticated($account);
            return false;
        }

        $this->sessionStorage->startChallenge($account, $redirectTarget);
        $this->secondFactorProvider->sendCode($account);

        return true;
    }

    public function getCodeFormUri(ServerRequestInterface $httpRequest): string
    {
        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest(ActionRequest::fromHttpRequest($httpRequest));

        return $uriBuilder->reset()->uriFor('secondFactor', [], 'Authentication', 'Networkteam.Neos.FrontendLogin');
    }
}
