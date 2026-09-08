<?php

declare(strict_types=1);

/***************************************************************
 *  (c) 2026 networkteam GmbH - all rights reserved
 ***************************************************************/

namespace Networkteam\Neos\FrontendLogin\Security;

use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\Neos\Security\Authorization\Privilege\ReadNodePrivilege;
use Neos\Flow\Annotations as Flow;

class PolicyConfiguration
{

    #[Flow\InjectConfiguration(path: 'roleSubtree')]
    protected array $roleSubtreeConfig = [];

    /**
     * @throws \InvalidArgumentException
     */
    public function addFrontendPrivilegesToPolicyConfiguration(array &$policyConfiguration): void
    {
        foreach ($this->roleSubtreeConfig as $roleIdentifier => $config) {
            if (empty($config['subtreeTags'])) {
                continue;
            }

            foreach ($config['subtreeTags'] as $subtreeTag) {
                if (empty($subtreeTag)) {
                    continue;
                }

                // Validate subtreeTag name. Throws \InvalidArgumentException if tag name does not regex pattern
                $subtreeTag = SubtreeTag::fromString($subtreeTag);

                // create privilege targets form configuration
                $privilegeTarget = 'Networkteam.Neos.Frontendlogin:ReadMemberAreaNodes.' . $subtreeTag;
                $policyConfiguration['privilegeTargets'][ReadNodePrivilege::class][$privilegeTarget] = [
                    'matcher' => (string)$subtreeTag,
                ];

                // grant created privileges to roles
                $policyConfiguration['roles'][$roleIdentifier]['privileges'][] = [
                    'privilegeTarget' => $privilegeTarget,
                    'permission' => 'GRANT'
                ];
            }
        }
    }
}