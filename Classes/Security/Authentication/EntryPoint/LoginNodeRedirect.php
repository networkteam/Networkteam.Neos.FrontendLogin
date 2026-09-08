<?php
namespace Networkteam\Neos\FrontendLogin\Security\Authentication\EntryPoint;

/***************************************************************
 *  (c) 2020 networkteam GmbH - all rights reserved
 ***************************************************************/

use GuzzleHttp\Psr7\Utils;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindClosestNodeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindReferencesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindSucceedingSiblingNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\ContentRepositoryRegistry\SubgraphCachingInMemory\SubgraphCachePool;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Security\Authentication\EntryPoint\WebRedirect;
use Neos\Flow\Security\Context;
use Neos\Neos\FrontendRouting\NodeUriBuilderFactory;
use Networkteam\Neos\FrontendLogin\Service\NodeAccessService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Find document node containing login form and redirect there.
 *
 * This entry point is usually activ when the requested node requires an authenticated user with certain access role
 *
 * @package Networkteam\Neos\FrontendLogin\Security\Authentication\EntryPoint
 */
class LoginNodeRedirect extends WebRedirect
{

    #[Flow\Inject]
    protected Context $securityContext;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected SubgraphCachePool $subgraphCachePool;

    #[Flow\Inject]
    protected NodeUriBuilderFactory $nodeUriBuilderFactory;

    #[Flow\Inject]
    protected LoggerInterface $systemLogger;

    public function startAuthentication(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $originalRequest = $this->securityContext->getInterceptedRequest();
        if ($originalRequest instanceof ActionRequest && $originalRequest->hasArgument('node')) {
            $nodeAddress = NodeAddress::fromJsonString((string)$originalRequest->getArgument('node'));
            $contentRepository = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString($nodeAddress->contentRepositoryId));
            // The requested node is not accessible in current security context. Therefor, we need to losen the VisibilityConstraints
            $structuralSubgraph = $this->subgraphCachePool->getContentSubgraph($contentRepository, $nodeAddress->workspaceName, $nodeAddress->dimensionSpacePoint, VisibilityConstraints::createEmpty());

            // Node is genuinely gone (deleted/disabled) - let NodeController throw its usual NodeNotFoundException (404).
            if ($structuralSubgraph->findNodeById($nodeAddress->aggregateId) === null) {
                return $response;
            }

            // find closest memberAreaRoot node to requested node
            $memberAreaRootNode = $structuralSubgraph->findClosestNode(
                $nodeAddress->aggregateId,
                FindClosestNodeFilter::create(nodeTypes: NodeAccessService::MEMBERAREAROOT_NODETYPE_NAME)
            );

            // MemberAreaRoot node was not found. Let NodeController throw its usual NodeNotFoundException (404).
            if ($memberAreaRootNode === null) {
                return $response;
            }

            // find login form page defined as reference on memberAreaRoot node
            $memberAreaLoginFormNode = $structuralSubgraph->findReferences(
                $memberAreaRootNode->aggregateId,
                FindReferencesFilter::create(referenceName: 'loginFormPage')
            )->getNodes()->first();

            // find default login form node by nodeType
            if ($memberAreaLoginFormNode === null) {
                $memberAreaLoginFormNode = $structuralSubgraph->findSucceedingSiblingNodes(
                    $memberAreaRootNode->aggregateId,
                    FindSucceedingSiblingNodesFilter::create(nodeTypes: 'Networkteam.Neos.FrontendLogin:Mixins.Login')
                )->first();
            }

            // Redirect to resolved login document node
            if ($memberAreaLoginFormNode instanceof Node) {
                $nodeUriBuilder = $this->nodeUriBuilderFactory->forActionRequest($originalRequest);
                $resolvedUri = $nodeUriBuilder->uriFor(
                    NodeAddress::fromNode($memberAreaLoginFormNode),
                );
                return $response
                    ->withBody(Utils::streamFor(sprintf(
                        '<html><head><meta http-equiv="refresh" content="0;url=%s"/></head></html>',
                        htmlentities((string)$resolvedUri, ENT_QUOTES, 'utf-8')
                    )))
                    ->withStatus(303)
                    ->withHeader('Location', (string)$resolvedUri);
            }
        }

        return $response;
    }
}