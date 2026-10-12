<?php
namespace Networkteam\Neos\FrontendLogin\Http;

/***************************************************************
 *  (c) 2026 networkteam GmbH - all rights reserved
 ***************************************************************/

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Context as SecurityContext;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorService;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorSessionStorage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Session gate for the second factor
 *
 * While the code is pending for the authenticated frontend account, only the code form, its actions and the logout
 * are reachable. Every other request is redirected to the code form. Runs after the security entry point, so routing
 * results and authentication tokens are available.
 */
class SecondFactorMiddleware implements MiddlewareInterface
{
    const PACKAGE_KEY = 'Networkteam.Neos.FrontendLogin';
    const CONTROLLER_NAME = 'Authentication';
    const ALLOWED_ACTIONS = ['secondfactor', 'verifysecondfactor', 'resendsecondfactor', 'logout'];

    /**
     * @Flow\Inject
     * @var SecurityContext
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var SecondFactorService
     */
    protected $secondFactorService;

    /**
     * @Flow\Inject
     * @var SecondFactorSessionStorage
     */
    protected $sessionStorage;

    /**
     * @Flow\InjectConfiguration(path="authenticationProviderName")
     * @var string
     */
    protected $authenticationProviderName;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. No authenticated frontend token, e.g. anonymous visitors or Neos backend requests
        $account = $this->getAuthenticatedAccount();
        if ($account === null) {
            return $handler->handle($request);
        }

        // 2. No second factor required or already passed
        if (!$this->secondFactorService->isRequired($account)) {
            return $handler->handle($request);
        }
        $status = $this->sessionStorage->getStatus($account);
        if ($status === SecondFactorSessionStorage::STATUS_AUTHENTICATED) {
            return $handler->handle($request);
        }

        $routingResults = $request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS) ?? [];

        // 3. The token was authenticated without passing the AuthenticationController: start the challenge here
        if ($status === null) {
            if ($this->isAction($routingResults, ['logout'])) {
                return $handler->handle($request);
            }

            $redirectTarget = strtoupper($request->getMethod()) === 'GET' ? (string)$request->getUri() : '/';
            if (!$this->secondFactorService->startChallenge($account, $redirectTarget, $request)) {
                // remembered browser
                return $handler->handle($request);
            }
        }

        // 4. Code pending: only the code form, its actions and the logout are allowed
        if ($this->isAction($routingResults, self::ALLOWED_ACTIONS)) {
            return $handler->handle($request);
        }

        // 5. Everything else is redirected to the code form
        $codeFormUri = $this->secondFactorService->getCodeFormUri($request);

        return new Response(303, ['Location' => $codeFormUri]);
    }

    protected function getAuthenticatedAccount(): ?Account
    {
        if (!$this->securityContext->canBeInitialized()) {
            return null;
        }

        $token = $this->securityContext->getAuthenticationTokens()[$this->authenticationProviderName] ?? null;
        if ($token === null || !$token->isAuthenticated()) {
            return null;
        }

        return $token->getAccount();
    }

    protected function isAction(array $routingResults, array $actionNames): bool
    {
        return strtolower($routingResults['@package'] ?? '') === strtolower(self::PACKAGE_KEY)
            && strtolower($routingResults['@controller'] ?? '') === strtolower(self::CONTROLLER_NAME)
            && in_array(strtolower($routingResults['@action'] ?? ''), $actionNames, true);
    }
}
