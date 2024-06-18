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
use Neos\Flow\Security\Authentication\Controller\AbstractAuthenticationController;
use Neos\Flow\Security\Authentication\TokenInterface;
use Neos\Flow\Security\Cryptography\HashService;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;
use Neos\Flow\Security\Exception\InvalidArgumentForHashGenerationException;
use Neos\Flow\Security\Exception\InvalidHashException;
use Networkteam\Neos\FrontendLogin\Helper\FlashMessageHelper;
use Networkteam\Neos\FrontendLogin\Helper\FlashMessageHelperFactory;
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
     * @param ActionRequest $originalRequest The request that was intercepted by the security framework, NULL if there was none
     * @return void
     */
    protected function onAuthenticationSuccess(ActionRequest $originalRequest = null)
    {
        if ($originalRequest !== null) {
            // Redirect to the location that redirected to the login form because the user was nog logged in
            $this->redirectToRequest($originalRequest);
        }

        try {
            $redirectAfterLoginUri = $this->hashService->validateAndStripHmac(
                $this->request->getArgument('redirectAfterLoginUri')
            );
        } catch (\Exception $e) {
            $redirectAfterLoginUri = $this->redirectOnLoginLogoutExceptionUri;
        }

        $this->redirectToUri($redirectAfterLoginUri);
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
            $redirectUriWithErrorParameter = $this->getRedirectOnErrorUri($this->request);
        } catch (\Exception $e) {
            $redirectUriWithErrorParameter = false;
            $this->getFlashMessageHelper()->addErrorMessage(
                'authentication.onAuthenticationFailure.redirectFailed',
                1617020324,
                [
                    'exceptionMessage' => $e->getMessage(),
                    'exceptionCode' => $e->getCode()
                ]
            );
        }

        // For $redirectUriWithErrorParameter being false the errorAction() should be called
        if ($redirectUriWithErrorParameter !== false) {
            try {
                $this->redirectToUri($redirectUriWithErrorParameter);
            } catch (\Neos\Flow\Security\Exception $e) {

            }
        }
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
