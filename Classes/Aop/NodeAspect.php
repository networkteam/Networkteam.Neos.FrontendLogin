<?php
namespace Networkteam\Neos\FrontendLogin\Aop;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Cache\Backend\SimpleFileBackend;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Error\Messages\Error;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\AOP\JoinPointInterface;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Http\Request;
use Neos\Flow\Http\Response;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\Arguments;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Networkteam\Neos\FrontendLogin\Service\MemberAreaRootNodePathCache;

/**
 * @Flow\Aspect
 */
class NodeAspect
{
    /**
     * @Flow\Inject
     * @var MemberAreaRootNodePathCache
     */
    protected $memberAreaRootNodePathCache;

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
     * @Flow\After("method(Neos\ContentRepository\TypeConverter\NodeConverter->convertFrom())")
     * @param JoinPointInterface $joinPoint
     */
    public function doSomething(JoinPointInterface $joinPoint)
    {
        $result = $joinPoint->getResult();

        // Could not convert array to Node object because the node "%s" does not exist.
        if ($result instanceof Error && $result->getCode() === 1370502328) {
            $methodArguments = $joinPoint->getMethodArguments();
            $source = $methodArguments['source'];
            $nodePathAndContext = NodePaths::explodeContextPath($source);
            $nodePath = $nodePathAndContext['nodePath'];

//            if ($this->memberAreaRootNodePathCache->hasCache()) {
//                $nodePathCache = $this->memberAreaRootNodePathCache->get();
//            } else {
//                $this->memberAreaRootNodePathCache->warmup($nodePath);
//            }

            /**
             * TODO: prüfen ob nodepath Teil von oder selber MemberAreaRoot ist
             * Wenn ja, dann finde MemberAreaRoot und nehme login node property und leite weiter zu login seite
             */


            //$nodePath von angefragem node ist vorahden

            $contentContext = $this->contextFactory->create([
                'workspaceName' => 'live',
                'invisibleContentShown' => false,
                'inaccessibleContentShown' => true
            ]);
            $memberAreaRootNode = null;
            $this->securityContext->withoutAuthorizationChecks(function () use ($nodePath, $contentContext, &$memberAreaRootNode) {
                $requestedNode = $contentContext->getNode($nodePath);
                $q = new FlowQuery([$requestedNode]);
                /** @var NodeInterface $memberAreaRootNode */
                $memberAreaRootNodes = $q->parents('[instanceof ' . MemberAreaRootNodePathCache::MEMBERAREAROOT_NODETYPE_NAME . ']');
                $memberAreaRootNode = $memberAreaRootNodes->get(0);
            });

            if ($memberAreaRootNode instanceof NodeInterface) {
                $loginFormPage = $memberAreaRootNode->getProperty('loginFormPage');
                $url = $this->getUrlToNode($loginFormPage);

                // TODO: handle redirect corretly with status code etc.
                header(sprintf('Location: %s', $url));

                throw new StopActionException();
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