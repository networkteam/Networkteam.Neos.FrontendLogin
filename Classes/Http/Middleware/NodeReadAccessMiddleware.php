<?php
declare(strict_types=1);

namespace Networkteam\Neos\FrontendLogin\Http\Middleware;

/***************************************************************
 *  (c) 2026 networkteam GmbH - all rights reserved
 ***************************************************************/

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindClosestNodeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindDescendantNodesFilter;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\ContentRepositoryRegistry\SubgraphCachingInMemory\SubgraphCachePool;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Mvc\ActionRequestFactory;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;
use Neos\Neos\Domain\SubtreeTagging\NeosVisibilityConstraints;
use Neos\Neos\FrontendRouting\Exception\NodeNotFoundException;
use Neos\Neos\FrontendRouting\NodeUriBuilderFactory;
use Neos\Neos\Security\Authorization\ContentRepositoryAuthorizationService;
use Networkteam\Neos\FrontendLogin\Service\NodeAccessService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Detects requests for a node that structurally exists but is denied to the current user's roles
 * via a role-based ReadNodePrivilege (e.g. a member area root), and throws AuthenticationRequiredException
 * so that the SecurityEntryPointMiddleware wrapping this middleware can dispatch to LoginNodeRedirect
 * (anonymous user) or fall through to a 403 response (authenticated user with the wrong role).
 *
 * Without this, NodeController::showAction() can't distinguish a role-denied node from a genuinely
 * missing one and always renders a 404.
 */
class NodeReadAccessMiddleware implements MiddlewareInterface
{
    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected SubgraphCachePool $subgraphCachePool;

    #[Flow\Inject]
    protected ContentRepositoryAuthorizationService $contentRepositoryAuthorizationService;

    #[Flow\Inject]
    protected SecurityContext $securityContext;

    #[Flow\Inject(lazy: false)]
    protected ActionRequestFactory $actionRequestFactory;

    #[Flow\Inject]
    protected NodeUriBuilderFactory $nodeUriBuilderFactory;

    #[Flow\InjectConfiguration(path: 'redirectToAccessibleMemberArea')]
    protected bool $redirectToAccessibleMemberArea;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        try {
            return $next->handle($request);
        } catch (NodeNotFoundException $nodeNotFoundException) {
            // Node was not found. Find out if the node is deleted or disabled
            $routingMatchResults = $request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS) ?? [];
            $actionRequest = $this->actionRequestFactory->createActionRequest($request, $routingMatchResults);
            $nodeAddress = NodeAddress::fromJsonString($actionRequest->getArgument('node'));
            $contentRepository = $this->contentRepositoryRegistry->get($nodeAddress->contentRepositoryId);
            $structuralVisibilityConstraints = NeosVisibilityConstraints::excludeRemoved()->merge(NeosVisibilityConstraints::excludeDisabled());
            $structuralSubgraph = $this->subgraphCachePool->getContentSubgraph($contentRepository, $nodeAddress->workspaceName, $nodeAddress->dimensionSpacePoint, $structuralVisibilityConstraints);

            // Node is genuinely gone (deleted/disabled) - let NodeController throw its usual NodeNotFoundException (404).
            if ($structuralSubgraph->findNodeById($nodeAddress->aggregateId) === null) {
                return $next->handle($request);
            }

            // Node is available. But for unauthenticated requests we throw AuthenticationRequiredException, which is
            // handled by SecurityEntryPointMiiddleware and redirects to login page
            $authenticatedAccount = $this->securityContext->getAccountByAuthenticationProviderName('Networkteam.Neos.FrontendLogin:Frontend');
            if ($authenticatedAccount === null || !$this->redirectToAccessibleMemberArea) {
                throw new AuthenticationRequiredException(
                    'Access to node is denied for the current user\'s roles.',
                    1756288000,
                    $nodeNotFoundException
                )->attachInterceptedRequest($actionRequest);
            }

            // Node exists, but the current role isn't granted a matching ReadNodePrivilege. We need to find the member
            // area node to which the account has access to.

            $accountVisibilityConstraints = $this->contentRepositoryAuthorizationService
                ->getVisibilityConstraints($contentRepository->id, $authenticatedAccount->getRoles())
                ->merge($structuralVisibilityConstraints);
            $accountSubgraph = $this->subgraphCachePool->getContentSubgraph($contentRepository, $nodeAddress->workspaceName, $nodeAddress->dimensionSpacePoint, $accountVisibilityConstraints);
            $siteNode = $accountSubgraph->findClosestNode($nodeAddress->aggregateId, FindClosestNodeFilter::create('Neos.Neos:Site'));
            if ($siteNode === null) {
                // Site node was not found. This should not happen.
                throw $nodeNotFoundException;
            }

            // find member area by role mapping config
            $memberAreaNode = $accountSubgraph->findDescendantNodes(
                $siteNode->aggregateId,
                FindDescendantNodesFilter::create(NodeAccessService::MEMBERAREAROOT_NODETYPE_NAME)
            )->first();
            if ($memberAreaNode === null) {
                throw new AuthenticationRequiredException(
                    'Failed to find accessible member area node for the current user\'s roles.',
                    1788867064,
                    $nodeNotFoundException
                )->attachInterceptedRequest($actionRequest);
            }

            // build uri to member area node
            $nodeUriBuilder = $this->nodeUriBuilderFactory->forActionRequest($actionRequest);
            $resolvedUri = $nodeUriBuilder->uriFor(
                NodeAddress::fromNode($memberAreaNode),
            );

            // redirect to member area node
            $response = new Response(303);
            return $response
                ->withBody(Utils::streamFor(sprintf(
                    '<html><head><meta http-equiv="refresh" content="0;url=%s"/></head></html>',
                    htmlentities((string)$resolvedUri, ENT_QUOTES, 'utf-8')
                )))
                ->withHeader('Location', (string)$resolvedUri);
        }
    }
}
