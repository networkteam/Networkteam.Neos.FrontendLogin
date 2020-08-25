<?php
namespace Networkteam\Neos\FrontendLogin\Security\Authentication\EntryPoint;

/***************************************************************
 *  (c) 2020 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Request;
use Neos\Flow\Http\Response;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\Arguments;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Authentication\EntryPoint\WebRedirect;
use Neos\Flow\Security\Cryptography\HashService;
use Neos\Neos\Domain\Service\ContentContext;
use Networkteam\Neos\FrontendLogin\Service\NodeAccessService;

/**
 * Find document node containing login form and redirect there.
 * There are two cases when this entry point is activated:
 *
 * 1. Having a authenticated user which does not have access to requested node
 * 2. The requested node requires an authenticated user with certain access role
 *
 * @package Networkteam\Neos\FrontendLogin\Security\Authentication\EntryPoint
 */
class LoginNodeRedirect extends WebRedirect
{

    /**
     * @Flow\Inject
     * @var \Neos\Flow\Security\Context
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var \Neos\Neos\Service\LinkingService
     */
    protected $linkingService;

    /**
     * @Flow\InjectConfiguration(path="authenticationProviderName")
     * @var string
     */
    protected $authenticationProviderName;

    /**
     * @Flow\InjectConfiguration(path="roleToMemberAreaMapping")
     * @var array
     */
    protected $roleToMemberAreaMapping;

    public function startAuthentication(Request $request, Response $response)
    {
        $originalRequest = $this->securityContext->getInterceptedRequest();

        if ($originalRequest->hasArgument('node')) {
            $contextPath = $originalRequest->getArgument('node');
            $memberAreaRootNode = $this->getMemberAreaRootNodeForAccount($contextPath, $this->getAccount());

            if ($memberAreaRootNode instanceof NodeInterface) {
                try {
                    $loginFormPage = $memberAreaRootNode->getProperty('loginFormPage');
                    if ($loginFormPage instanceof NodeInterface) {
                        $uri = $this->createNodeUri($request, $loginFormPage);
                        $response->setContent(sprintf('<html><head><meta http-equiv="refresh" content="0;url=%s"/></head></html>', htmlentities($uri, ENT_QUOTES, 'utf-8')));
                        $response->withStatus(303);
                        $response->withHeader('Location', $uri);
                    }
                } catch (\Exception $e) {

                }
            }
        }
    }

    /**
     * Create the frontend URL to a node
     *
     * @throws \Neos\Neos\Exception
     */
    protected function createNodeUri(Request $request, NodeInterface $node, array $arguments = []): string
    {
        // initialize uriBuilder
        $actionRequest = new ActionRequest($request);
        $this->uriBuilder->setRequest($actionRequest);

        $controllerContext = new ControllerContext(
            $this->uriBuilder->getRequest(),
            new Response(),
            new Arguments([]),
            $this->uriBuilder
        );

        return $this->linkingService->createNodeUri(
            $controllerContext,
            $node,
            $node->getContext()->getRootNode(),
            'html',
            true,
            $arguments
        );
    }

    protected function getMemberAreaRootNodeForAccount($contextPath, ?Account $account = null): ?NodeInterface
    {
        $memberAreaRootNode = null;
        $nodePathAndContext = NodePaths::explodeContextPath($contextPath);
        $nodePath = $nodePathAndContext['nodePath'];
        $contentContext = $this->createContext($nodePathAndContext['workspaceName'], $nodePathAndContext['dimensions']);
        $isAuthenticated = $account instanceof Account;

        if ($isAuthenticated) {
            // find MemberAreaRoot node authenticated user can access
            $memberAreaRootNodeType = $this->getMemberAreaNodeTypeForAccount($account);
            if ($memberAreaRootNodeType) {
                $q = new FlowQuery([$contentContext->getCurrentSiteNode()]);
                $memberAreaRootNode = $q->find(sprintf('[instanceof %s]', $memberAreaRootNodeType))->get(0);
            }
        } else {
            // find node by disabling authorization checks (CSRF token, policies, content security, ...)
            $this->securityContext->withoutAuthorizationChecks(function () use ($nodePath, $contentContext, &$memberAreaRootNode) {
                try {
                    $requestedNode = $contentContext->getNode($nodePath);
                    if ($requestedNode instanceof NodeInterface) {
                        // find closest MemberAreaRoot node starting from requested node an traversing all parents
                        $q = new FlowQuery([$requestedNode]);
                        $memberAreaRootNode = $q->closest(sprintf('[instanceof %s]', NodeAccessService::MEMBERAREAROOT_NODETYPE_NAME))->get(0);
                    }
                } catch (\Exception $e) {
                }
            });
        }

        return $memberAreaRootNode;
    }

    protected function createContext($workspaceName, array $dimensions = null): ContentContext
    {
        $contextConfiguration = array(
            'workspaceName' => $workspaceName,
            'invisibleContentShown' => false,
            'inaccessibleContentShown' => true
        );

        if ($dimensions !== null) {
            $contextConfiguration['dimensions'] = $dimensions;
        }

        return $this->contextFactory->create($contextConfiguration);
    }

    protected function getMemberAreaNodeTypeForAccount(Account $account): ?string
    {
        $memberAreaRootNodeType = null;
        /** @var \Neos\Flow\Security\Policy\Role $role */
        foreach ($account->getRoles() as $role) {
            if (!empty($this->roleToMemberAreaMapping[$role->getIdentifier()])) {
                $memberAreaRootNodeType = $this->roleToMemberAreaMapping[$role->getIdentifier()];
                break;
            }
        }
        return $memberAreaRootNodeType;
    }

    /**
     * Return authenticated FrontendLogin account
     *
     * @return Account|null
     */
    public function getAccount(): ?Account
    {
        if ($this->securityContext->canBeInitialized() === true) {
            $account = $this->securityContext->getAccountByAuthenticationProviderName($this->authenticationProviderName);

            return $account;
        }

        return null;
    }
}