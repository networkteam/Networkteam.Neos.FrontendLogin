<?php
namespace Networkteam\Neos\FrontendLogin\Controller;

/***************************************************************
 *  (c) 2019 networkteam GmbH - all rights reserved
 ***************************************************************/

use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Helper\UriHelper;
use Neos\Flow\I18n\Locale;
use Neos\Flow\I18n\Service;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Mvc\RequestInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Authentication\Controller\AbstractAuthenticationController;
use Neos\Flow\Security\Authentication\TokenInterface;
use Neos\Flow\Security\Cryptography\HashService;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;
use Neos\Flow\Security\Exception\InvalidArgumentForHashGenerationException;
use Neos\Flow\Security\Exception\InvalidHashException;
use Neos\Flow\Security\Policy\PolicyService;
use Neos\Neos\View\FusionView as NeosFusionView;
use Networkteam\Neos\FrontendLogin\Helper\FlashMessageHelper;
use Networkteam\Neos\FrontendLogin\Helper\FlashMessageHelperFactory;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\RememberedBrowserService;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorProviderInterface;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorService;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorSessionStorage;
use Networkteam\Neos\FrontendLogin\Service\MemberAreaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class AuthenticationController extends AbstractAuthenticationController
{

    /**
     * @Flow\Inject
     * @var Service
     */
    protected $i18nService;

    /**
     * @Flow\InjectConfiguration(package="Networkteam.Neos.FrontendLogin", path="redirectOnLoginLogoutExceptionUri")
     * @var string
     */
    protected $redirectOnLoginLogoutExceptionUri;

    /**
     * @var HashService
     * @Flow\Inject
     */
    protected $hashService;

    /**
     * @Flow\InjectConfiguration(path="authenticationProviderName")
     * @var string
     */
    protected $authenticationProviderName;

    /**
     * @Flow\Inject
     * @var PolicyService
     */
    protected $policyService;

    /**
     * @Flow\Inject
     * @var SecondFactorService
     */
    protected $secondFactorService;

    /**
     * @Flow\Inject
     * @var SecondFactorSessionStorage
     */
    protected $secondFactorSessionStorage;

    /**
     * @Flow\Inject
     * @var SecondFactorProviderInterface
     */
    protected $secondFactorProvider;

    /**
     * @Flow\Inject
     * @var RememberedBrowserService
     */
    protected $rememberedBrowserService;

    /**
     * @Flow\Inject
     * @var MemberAreaService
     */
    protected $memberAreaService;

    public function initializeAction()
    {
        parent::initializeAction();

        try {
            $localeIdentifier = $this->request->getArgument('locale');
            $currentLocale = new Locale($localeIdentifier);
            $this->i18nService->getConfiguration()->setCurrentLocale($currentLocale);
        } catch (NoSuchArgumentException $e) {

        }
    }

    /**
     * @Flow\SkipCsrfProtection
     */
    public function logoutAction()
    {
        foreach ($this->authenticationManager->getSecurityContext()->getAuthenticationTokens() as $token) {
            // logout only frontend token
            if ($token->getAuthenticationProviderName() == $this->authenticationProviderName) {
                $token->setAuthenticationStatus(TokenInterface::NO_CREDENTIALS_GIVEN);
            }
        }
        $this->secondFactorSessionStorage->reset();

        try {
            $redirectAfterLogoutUri = $this->hashService->validateAndStripHmac(
                $this->request->getArgument('redirectAfterLogoutUri')
            );
        } catch (\Exception $e) {
            $redirectAfterLogoutUri = $this->redirectOnLoginLogoutExceptionUri;
        }

        $this->redirectToUri($redirectAfterLogoutUri);
    }

    /**
     * Show the second factor code form, rendered with the site Fusion and the account's portal root as context node
     */
    public function secondFactorAction(): string
    {
        $account = $this->getFrontendAccount();
        if ($account === null || !$this->secondFactorSessionStorage->isPending($account)) {
            $this->redirectToUri($this->redirectOnLoginLogoutExceptionUri);
        }

        $view = new NeosFusionView();
        $view->setControllerContext($this->controllerContext);
        $view->setFusionPath('networkteamFrontendLoginSecondFactor');
        $view->assign('value', $this->memberAreaService->getPortalRootNode($account));
        $output = $view->render();

        // Neos.Neos:Page renders a complete HTTP message
        if ($output instanceof ResponseInterface) {
            $this->response->setStatusCode($output->getStatusCode());
            foreach ($output->getHeaders() as $headerName => $headerValues) {
                $this->response->setHttpHeader($headerName, $headerValues);
            }
            $output = (string)$output->getBody();
        }
        $this->response->setHttpHeader('Cache-Control', 'no-store');

        return $output;
    }

    public function verifySecondFactorAction(string $code = '', bool $rememberBrowser = false): void
    {
        $account = $this->getFrontendAccount();
        if ($account === null || !$this->secondFactorSessionStorage->isPending($account)) {
            $this->redirectToUri($this->redirectOnLoginLogoutExceptionUri);
        }

        if (!$this->secondFactorProvider->verifyCode($account, trim($code))) {
            $this->getFlashMessageHelper()->addErrorMessage('secondFactor.verify.failed', 1792047611);
            $this->redirectToUri($this->secondFactorService->getCodeFormUri($this->request->getHttpRequest()));
        }

        $redirectTarget = $this->secondFactorSessionStorage->getRedirectTarget($account) ?? $this->redirectOnLoginLogoutExceptionUri;
        $this->secondFactorSessionStorage->markAuthenticated($account);

        if ($rememberBrowser && $this->rememberedBrowserService->isEnabled()) {
            $this->response->setCookie($this->rememberedBrowserService->createCookie($account));
        }

        $this->redirectToUri($redirectTarget);
    }

    public function resendSecondFactorAction(): void
    {
        $account = $this->getFrontendAccount();
        if ($account === null || !$this->secondFactorSessionStorage->isPending($account)) {
            $this->redirectToUri($this->redirectOnLoginLogoutExceptionUri);
        }

        $this->secondFactorProvider->sendCode($account);
        $this->getFlashMessageHelper()->addNoticeMessage('secondFactor.resend.sent', 1792047612);

        $this->redirectToUri($this->secondFactorService->getCodeFormUri($this->request->getHttpRequest()));
    }

    /**
     * @param ActionRequest $originalRequest The request that was intercepted by the security framework, NULL if there was none
     * @return void
     */
    protected function onAuthenticationSuccess(ActionRequest $originalRequest = null)
    {
        $account = $this->getFrontendAccount();
        if ($account !== null && $this->secondFactorService->isRequired($account)) {
            $redirectTarget = $originalRequest !== null ? (string)$originalRequest->getHttpRequest()->getUri() : $this->getRedirectAfterLoginUri();
            $httpRequest = $this->request->getHttpRequest();

            if ($this->secondFactorService->startChallenge($account, $redirectTarget, $httpRequest)) {
                $this->redirectToUri($this->secondFactorService->getCodeFormUri($httpRequest));
            }
            $this->redirectToUri($redirectTarget);
        }

        if ($originalRequest !== null) {
            // Redirect to the location that redirected to the login form because the user was nog logged in
            $this->redirectToRequest($originalRequest);
        }

        $this->redirectToUri($this->getRedirectAfterLoginUri());
    }

    protected function getRedirectAfterLoginUri(): string
    {
        try {
            $redirectAfterLoginUri = $this->hashService->validateAndStripHmac(
                $this->request->getArgument('redirectAfterLoginUri')
            );

            // get redirect uri for first successful authentication
            try {
                $hasFirstSuccessfulAuthenticationRole = $this->authenticationManager->getSecurityContext()->getAccount()->hasRole(
                    $this->policyService->getRole('Networkteam.Neos.FrontendLogin:FirstSuccessfulAuthentication')
                );

                if ($hasFirstSuccessfulAuthenticationRole && $this->request->hasArgument('redirectAfterFirstLoginUri')) {
                    $redirectAfterLoginUri = $this->hashService->validateAndStripHmac(
                        $this->request->getArgument('redirectAfterFirstLoginUri')
                    );
                }
            } catch (\Exception $e) {

            }
        } catch (\Exception $e) {
            $redirectAfterLoginUri = $this->redirectOnLoginLogoutExceptionUri;
        }

        return $redirectAfterLoginUri;
    }

    protected function getFrontendAccount(): ?Account
    {
        return $this->authenticationManager->getSecurityContext()->getAccountByAuthenticationProviderName($this->authenticationProviderName);
    }

    /**
     * Create translated FlashMessage and add it to flashMessageContainer
     *
     * @param AuthenticationRequiredException $exception
     * @return void
     */
    protected function onAuthenticationFailure(AuthenticationRequiredException $exception = null)
    {
        $this->getFlashMessageHelper()->addErrorMessage('authentication.onAuthenticationFailure.authenticationFailed', 1566923371);

        // build and validate redirect uri
        try {
            $redirectUri = $this->getRedirectOnErrorUri($this->request);
        } catch (\Exception $e) {
            $redirectUri = $this->redirectOnLoginLogoutExceptionUri;
            $this->getFlashMessageHelper()->addErrorMessage(
                'authentication.onAuthenticationFailure.redirectFailed',
                1617020324,
                [
                    'exceptionMessage' => $e->getMessage(),
                    'exceptionCode' => $e->getCode()
                ]
            );
        }

        $this->redirectToUri($redirectUri);
    }

    protected function validateHmac(string $string): bool
    {
        try {
            $this->hashService->validateAndStripHmac($string);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Disable the technical error flash message
     *
     * @return boolean
     */
    protected function getErrorFlashMessage()
    {
        return false;
    }

    /**
     * @param RequestInterface $request
     * @return UriInterface
     * @throws InvalidArgumentForHashGenerationException
     * @throws InvalidHashException
     * @throws NoSuchArgumentException
     */
    protected function getRedirectOnErrorUri(RequestInterface $request): UriInterface
    {
        $redirectOnErrorUriString = $this->hashService->validateAndStripHmac(
            $request->getArgument('redirectOnErrorUri')
        );

        $redirectUri = new Uri($redirectOnErrorUriString);
        $arguments = [
            'error' => 'authenticationFailed'
        ];

        // validate redirectAfterLoginUri request argument and add it to redirectOnErrorUri arguments
        if ($this->validateHmac($this->request->getArgument('redirectAfterLoginUri'))) {
            $arguments['redirectAfterLoginUri'] = $request->getArgument('redirectAfterLoginUri');
        }

        return UriHelper::uriWithArguments($redirectUri, $arguments);
    }

    protected function errorAction()
    {
        return sprintf(
            '%s<br />%s',
            parent::errorAction(),
            implode("<br />", $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush())
        );
    }

    protected function getFlashMessageHelper(): FlashMessageHelper
    {
        return FlashMessageHelperFactory::create($this->request);
    }
}
