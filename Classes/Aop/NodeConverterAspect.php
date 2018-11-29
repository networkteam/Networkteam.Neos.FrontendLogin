<?php
namespace Networkteam\Neos\FrontendLogin\Aop;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Error\Messages\Error;
use Neos\Flow\AOP\JoinPointInterface;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Http\Request;
use Neos\Flow\Http\Response;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\Arguments;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Security\Cryptography\HashService;

/**
 * @Flow\Aspect
 */
class NodeConverterAspect
{
    const MEMBERAREAROOT_NODETYPE_NAME = 'Networkteam.Neos.FrontendLogin:Mixins.MemberAreaRoot';

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var \Neos\Flow\Security\Context
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var \Neos\Neos\Service\LinkingService
     */
    protected $linkingService;

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @var HashService
     * @Flow\Inject
     */
    protected $hashService;

    /**
     * The injection of the faked UriBuilder is necessary to generate frontend URLs from the backend
     *
     * @param ConfigurationManager $configurationManager
     */
    public function injectUriBuilder(ConfigurationManager $configurationManager)
    {
        $_SERVER['FLOW_REWRITEURLS'] = 1;
        $httpRequest = Request::createFromEnvironment();
        $request = new ActionRequest($httpRequest);
        $uriBuilder = new UriBuilder();
        $uriBuilder->setRequest($request);
        $uriBuilder->setCreateAbsoluteUri(true);
        $this->uriBuilder = $uriBuilder;
    }

    /**
     * Try to find page with login form and redirect to it.
     *
     * @Flow\After("method(Neos\ContentRepository\TypeConverter\NodeConverter->convertFrom())")
     * @param JoinPointInterface $joinPoint
     */
    public function redirectToLoginPage(JoinPointInterface $joinPoint)
    {
        $result = $joinPoint->getResult();

        // requested node could not be found
        if ($result instanceof Error && $result->getCode() === 1370502328) {
            $methodArguments = $joinPoint->getMethodArguments();
            $source = $methodArguments['source'];

            if (is_array($source)) {
                $source = $source['__contextNodePath'];
            }

            $nodePathAndContext = NodePaths::explodeContextPath($source);
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
                    if ($requestedNode->getNodeType()->isOfType(self::MEMBERAREAROOT_NODETYPE_NAME)) {
                        $memberAreaRootNode = $requestedNode;
                    } else {
                        $q = new FlowQuery([$requestedNode]);
                        $memberAreaRootNode = $q->parents('[instanceof ' . self::MEMBERAREAROOT_NODETYPE_NAME . ']')->get(0);
                    }
                }
            });

            if ($memberAreaRootNode instanceof NodeInterface) {
                try {
                    $loginFormPage = $memberAreaRootNode->getProperty('loginFormPage');
                    if ($loginFormPage instanceof NodeInterface) {
                        $arguments = [];
                        if ($requestedNode instanceof NodeInterface) {
                            $requestedNodeUri = $this->getUrlToNode($requestedNode);
                            $referer = $this->hashService->appendHmac($requestedNodeUri);
                            $arguments['referer'] = $referer;
                        }
                        $url = $this->getUrlToNode($loginFormPage, $arguments);

                        // TODO: handle redirect correctly with status code etc.
                        header(sprintf('Location: %s', $url));
                        exit;
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
    protected function getUrlToNode(NodeInterface $node, array $arguments = []): string
    {
        $uri = $this->linkingService->createNodeUri(
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
        return $uri;
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
}