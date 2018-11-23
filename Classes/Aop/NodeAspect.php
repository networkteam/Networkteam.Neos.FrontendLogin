<?php
namespace Networkteam\Neos\FrontendLogin\Aop;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Error\Messages\Error;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\AOP\JoinPointInterface;

/**
 * @Flow\Aspect
 */
class NodeAspect
{

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

            /**
             * TODO: prüfen ob nodepath Teil von oder selber MemberAreaRoot ist
             * Wenn ja, dann finde MemberAreaRoot und nehme login node property und leite weiter zu login seite
             */
        }
    }
}