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

/**
 * @Flow\Aspect
 */
class NodeAspect
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
            $nodePathAndContext = NodePaths::explodeContextPath($source);
            $nodePath = $nodePathAndContext['nodePath'];
            $memberAreaRootNode = null;
            $contentContext = $this->contextFactory->create([
                'workspaceName' => 'live',
                'invisibleContentShown' => false,
                'inaccessibleContentShown' => true
            ]);

            // try to find node by disabling authorization checks (CSRF token, policies, content security, ...)
            $this->securityContext->withoutAuthorizationChecks(function () use ($nodePath, $contentContext, &$memberAreaRootNode) {
                $requestedNode = $contentContext->getNode($nodePath);
                $q = new FlowQuery([$requestedNode]);
                /** @var NodeInterface $memberAreaRootNode */
                $memberAreaRootNodes = $q->parents('[instanceof ' . self::MEMBERAREAROOT_NODETYPE_NAME . ']');
                $memberAreaRootNode = $memberAreaRootNodes->get(0);
            });

            if ($memberAreaRootNode instanceof NodeInterface) {
                try {
                    $loginFormPage = $memberAreaRootNode->getProperty('loginFormPage');

                    if ($loginFormPage instanceof NodeInterface) {
                        $url = $this->getUrlToNode($loginFormPage);

                        // TODO: handle redirect corretly with status code etc.
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
     * @param NodeInterface $node
     * @return string The URL of the node
     * @throws \Neos\Neos\Exception
     */
    protected function getUrlToNode(NodeInterface $node)
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
            true
        );
        return $uri;
    }

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
}