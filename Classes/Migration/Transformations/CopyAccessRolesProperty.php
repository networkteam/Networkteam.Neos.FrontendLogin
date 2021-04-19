<?php
namespace Networkteam\Neos\FrontendLogin\Migration\Transformations;

use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Migration\Transformations\AbstractTransformation;

class CopyAccessRolesProperty extends AbstractTransformation
{

    /**
     * @var string
     */
    protected $newPropertyName;

    /**
     * @param string $newPropertyName
     */
    public function setNewPropertyName(string $newPropertyName): void
    {
        $this->newPropertyName = $newPropertyName;
    }

    /**
     * Sets the name of the property to change.
     *
     * @param string $propertyName
     * @return void
     */
    public function setProperty($propertyName)
    {
        $this->propertyName = $propertyName;
    }

    /**
     * If the given node has the property this transformation should work on, this
     * returns true.
     *
     * @param NodeData $node
     * @return boolean
     */
    public function isTransformable(NodeData $node)
    {
        if (!method_exists($node, 'getAccessRoles')) {
            return false;
        }

        return (!empty($node->getAccessRoles()) && !$node->hasProperty($this->newPropertyName));
    }

    /**
     * Change the property on the given node.
     *
     * @param NodeData $node
     * @return void
     */
    public function execute(NodeData $node)
    {
        $node->setProperty($this->newPropertyName, $node->getAccessRoles());
    }
}
