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
use Neos\Flow\Security\Authentication\EntryPoint\WebRedirect;
use Neos\Flow\Security\Cryptography\HashService;
use Networkteam\Neos\FrontendLogin\Service\NodeAccessService;

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
     * @var HashService
     * @Flow\Inject
     */
    protected $hashService;

    public function startAuthentication(Request $request, Response $response)
    {
        // initialize uriBuilder
        $actionRequest = new ActionRequest($request);
        $this->uriBuilder->setRequest($actionRequest);


        // is authenticated

        //TODO:


        // is NOT authenticated

        // TODO: find memberAreaNode starting at original requested node
        // get configured login page from memberAreaNode
        $originalRequest = $this->securityContext->getInterceptedRequest();

        if ($originalRequest->hasArgument('node')) {
            $contextPath = $originalRequest->getArgument('node');
            $nodePathAndContext = NodePaths::explodeContextPath($contextPath);
            $nodePath = $nodePathAndContext['nodePath'];
            $workspaceName = $nodePathAndContext['workspaceName'];
            $dimensions = $nodePathAndContext['dimensions'];
            $contentContext = $this->contextFactory->create($this->prepareContextProperties($workspaceName, $dimensions));
            $memberAreaRootNode = null;
            $requestedNode = null;

            // try to find node by disabling authorization checks (CSRF token, policies, content security, ...)
            $this->securityContext->withoutAuthorizationChecks(function () use ($nodePath, $contentContext, &$memberAreaRootNode, &$requestedNode) {
                try {
                    $requestedNode = $contentContext->getNode($nodePath);
                } catch (\Exception $e) {
                }

                if ($requestedNode instanceof NodeInterface) {
                    if ($requestedNode->getNodeType()->isOfType(NodeAccessService::MEMBERAREAROOT_NODETYPE_NAME)) {
                        $memberAreaRootNode = $requestedNode;
                    } else {
                        $q = new FlowQuery([$requestedNode]);
                        $memberAreaRootNode = $q->parents('[instanceof ' . NodeAccessService::MEMBERAREAROOT_NODETYPE_NAME . ']')->get(0);
                    }
                }
            });

            if ($memberAreaRootNode instanceof NodeInterface) {
                try {
                    $loginFormPage = $memberAreaRootNode->getProperty('loginFormPage');
                    if ($loginFormPage instanceof NodeInterface) {
                        $arguments = [];

                        //TODO: must not be done because the original request is saved in security context which can be used in authentication controller
//                        if ($requestedNode instanceof NodeInterface) {
//                            $requestedNodeUri = $this->getUrlToNode($requestedNode);
//                            $referer = $this->hashService->appendHmac($requestedNodeUri);
//                            $arguments['referer'] = $referer;
//                        }

                        $uri = $this->getUrlToNode($loginFormPage, $arguments);
                        $response->setContent(sprintf('<html><head><meta http-equiv="refresh" content="0;url=%s"/></head></html>', htmlentities($uri, ENT_QUOTES, 'utf-8')));
                        $response->withStatus(303);
                        $response->withHeader('Location', $uri);
                    }
                } catch (\Exception $e) {

                }
            }

        } else {
            // TODO: ?
        }
    }

    protected function prepareContextProperties($workspaceName, array $dimensions = null): array
    {
        $contextProperties = array(
            'workspaceName' => $workspaceName,
            'invisibleContentShown' => false,
            'inaccessibleContentShown' => true
        );

        if ($dimensions !== null) {
            $contextProperties['dimensions'] = $dimensions;
        }

        return $contextProperties;
    }

    /**
     * Create the frontend URL to a node
     *
     * @throws \Neos\Neos\Exception
     */
    protected function getUrlToNode(NodeInterface $node, array $arguments = []): string
    {
        return $this->linkingService->createNodeUri(
            new ControllerContext(
                $this->uriBuilder->getRequest(),
                new Response(),
                new Arguments([]),
                $this->uriBuilder
            ),
            $node,
            $node->getContext()->getRootNode(),
            'html',
            true,
            $arguments
        );
    }

}