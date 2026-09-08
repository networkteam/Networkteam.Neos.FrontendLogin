<?php

declare(strict_types=1);

/***************************************************************
 *  (c) 2026 networkteam GmbH - all rights reserved
 ***************************************************************/

namespace Networkteam\Neos\FrontendLogin\Command;

use Neos\ContentRepository\Core\Feature\SubtreeTagging\Command\TagSubtree;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Exception\SubtreeIsAlreadyTagged;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeVariantSelectionStrategy;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Annotations as Flow;

final class ContentCommandController extends CommandController
{
    #[Flow\InjectConfiguration(package: 'Networkteam.Neos.FrontendLogin', path: 'roleSubtree')]
    protected array $roleSubtreeConfig = [];

    public function __construct(
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry
    ) {
        parent::__construct();
    }

    /**
     * Adds subtree tags for configured roles and nodeTypes
     */
    public function applySubtreeTagsCommand(string $contentRepository = 'default', string $workspace = WorkspaceName::WORKSPACE_NAME_LIVE)
    {
        $contentRepositoryInstance = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString($contentRepository));
        $workspaceName = WorkspaceName::fromString($workspace);

        foreach ($this->roleSubtreeConfig as $roleIdentifier => $roleConfig) {
            $roleConfig['rootNodeType'];
            $roleConfig['subtreeTags'];

            $nodeAggregates = $contentRepositoryInstance->getContentGraph($workspaceName)->findNodeAggregatesByType(
                NodeTypeName::fromString($roleConfig['rootNodeType'])
            );

            if ($nodeAggregates->count() == 0) {
                $this->outputLine('<error>Failed to find nodes of type %s</error>', [$roleConfig['rootNodeType']]);
                $this->quit(1);
            }

            foreach ($nodeAggregates as $nodeAggregate) {
                foreach ($roleConfig['subtreeTags'] as $subtreeTag) {
                    try {
                        $this->outputLine('<info>Add subtree tag "%s" to node aggregate "%s" (%s).</info>', [$subtreeTag, $nodeAggregate->nodeAggregateId, $nodeAggregate->nodeTypeName]);

                        $contentRepositoryInstance->handle(TagSubtree::create(
                            $workspaceName,
                            $nodeAggregate->nodeAggregateId,
                            array_values($nodeAggregate->coveredDimensionSpacePoints->points)[0],
                            NodeVariantSelectionStrategy::STRATEGY_ALL_VARIANTS,
                            SubtreeTag::fromString($subtreeTag)
                        ));
                    } catch (SubtreeIsAlreadyTagged $exception) {
                        $this->outputLine('<error>%s</error>', [$exception->getMessage()]);
                    }
                }
            }
        }
    }
}