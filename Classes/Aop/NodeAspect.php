<?php
namespace Networkteam\Neos\FrontendLogin\Aop;

/***************************************************************
 *  (c) 2018 networkteam GmbH - all rights reserved
 ***************************************************************/

use Neos\Flow\Annotations as Flow;
use Neos\Flow\AOP\JoinPointInterface;

/**
 * @Flow\Aspect
 */
class NodeAspect
{

    /**
     * \Neos\ContentRepository\TypeConverter\NodeConverter::convertFrom
     * \Neos\ContentRepository\Domain\Service\Context::getNode
     */

    /**
     * @Flow\AfterReturning("method(Neos\ContentRepository\Domain\Service\Context->getNode())")
     * @param JoinPointInterface $joinPoint
     */
    public function doSomething(JoinPointInterface $joinPoint)
    {
        $foo = 'bar';
    }
}