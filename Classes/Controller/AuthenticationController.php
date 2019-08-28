<?php
namespace Networkteam\Neos\FrontendLogin\Controller;

/***************************************************************
 *  (c) 2019 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Error\Messages\Error;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Security\Authentication\Controller\AbstractAuthenticationController;
use Neos\Flow\Security\Cryptography\HashService;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;


class AuthenticationController extends AbstractAuthenticationController
{
    /**
     * @var Translator
     * @Flow\Inject
     */
    protected $translator;

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

            $error = new Error(
                $this->translator->translateById('authentication.onAuthenticationFailure.authenticationFailed', [], null, null, 'Main', 'Networkteam.Neos.FrontendLogin'),
                1566923371
            );
            $this->flashMessageContainer->addMessage($error);

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
