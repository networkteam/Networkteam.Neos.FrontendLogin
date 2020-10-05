<?php
namespace Networkteam\Neos\FrontendLogin\Controller;

/***************************************************************
 *  (c) 2019 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Security\Authentication\Controller\AbstractAuthenticationController;
use Neos\Flow\Security\Cryptography\HashService;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;
use Networkteam\Neos\FrontendLogin\Helper\FlashMessageHelper;

class AuthenticationController extends AbstractAuthenticationController
{
    /**
     * @Flow\Inject
     * @var FlashMessageHelper
     */
    protected $flashMessageHelper;

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
     * @Flow\SkipCsrfProtection
     */
    public function logoutAction()
    {
        parent::logoutAction();

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
        $this->flashMessageHelper->addErrorMessage('authentication.onAuthenticationFailure.authenticationFailed', 1566923371);

        try {
            $redirectUriString = $this->hashService->validateAndStripHmac(
                $this->request->getArgument('redirectOnErrorUri')
            );

            $redirectUri = new \Neos\Flow\Http\Uri($redirectUriString);
            $redirectUriWithErrorParameter = \Neos\Flow\Http\Helper\UriHelper::uriWithArguments(
                $redirectUri,
                [
                    'error' => 'authenticationFailed'
                ]
            );

            $this->redirectToUri($redirectUriWithErrorParameter);
        } catch (\Neos\Flow\Security\Exception $e) {

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
}
