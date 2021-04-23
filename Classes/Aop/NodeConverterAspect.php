<?php
namespace Networkteam\Neos\FrontendLogin\Aop;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Error\Messages\Error;
use Neos\Flow\AOP\JoinPointInterface;
use Neos\Flow\Http\Request;
use Neos\Flow\Security\Exception\AuthenticationRequiredException;

/**
 * @Flow\Aspect
 */
class NodeConverterAspect
{
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
     * Check requested node for required authentication and throw AuthenticationRequiredException
     *
     * @Flow\After("method(Neos\ContentRepository\TypeConverter\NodeConverter->convertFrom())")
     * @param JoinPointInterface $joinPoint
     * @see https://flowframework.readthedocs.io/en/5.3/TheDefinitiveGuide/PartIII/Security.html?highlight=entrypoint#authentication-entry-points
     */
    public function checkForRequiredAuthentication(JoinPointInterface $joinPoint)
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

            // try to find node by disabling authorization checks (CSRF token, policies, content security, ...)
            $this->securityContext->withoutAuthorizationChecks(function () use ($nodePath, $contentContext) {
                try {
                    $requestedNode = $contentContext->getNode($nodePath);
                } catch (\Exception $e) {
                    // Node could not be found. Exception is caught so this aspect does not change workflow.
                    $requestedNode = null;
                }

                // throw AuthenticationRequiredException so that configured EntryPoint can take action
                if ($requestedNode instanceof NodeInterface && $requestedNode->hasAccessRestrictions() && !$requestedNode->isAccessible()) {
                    throw new AuthenticationRequiredException('Requested node is available but has access restrictions.', 1598341784);
                }
            });
        }
    }

    protected function prepareContextProperties(string $workspaceName, array $dimensions = null): array
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
