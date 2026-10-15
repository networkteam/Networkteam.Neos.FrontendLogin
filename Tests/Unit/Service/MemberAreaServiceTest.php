<?php
namespace Networkteam\Neos\FrontendLogin\Tests\Unit\Service;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Flow\Security\Account;
use Neos\Flow\Tests\UnitTestCase;
use Neos\Neos\Domain\Service\ContentContext;
use Networkteam\Neos\FrontendLogin\Service\MemberAreaService;
use PHPUnit\Framework\MockObject\MockObject;

class MemberAreaServiceTest extends UnitTestCase
{
    /**
     * @var MemberAreaService|MockObject
     */
    protected $service;

    /**
     * @var NodeInterface|MockObject
     */
    protected $siteNode;

    protected function setUp(): void
    {
        $this->siteNode = $this->createNode(false, null);
        $contentContext = $this->createMock(ContentContext::class);
        $contentContext->method('getCurrentSiteNode')->willReturn($this->siteNode);

        $this->service = $this->getMockBuilder(MemberAreaService::class)
            ->onlyMethods(['createLiveContext', 'findMemberAreaRootNodeForAccount'])
            ->getMock();
        $this->service->method('createLiveContext')->willReturn($contentContext);
    }

    /**
     * @test
     */
    public function portalRootIsClosestPortalRootAboveMemberAreaRoot(): void
    {
        $portalRoot = $this->createNode(true, $this->siteNode);
        $memberAreaRoot = $this->createNode(false, $portalRoot);
        $this->service->method('findMemberAreaRootNodeForAccount')->willReturn($memberAreaRoot);

        self::assertSame($portalRoot, $this->service->getPortalRootNode(new Account()));
    }

    /**
     * @test
     */
    public function memberAreaRootCanBeThePortalRoot(): void
    {
        $memberAreaRoot = $this->createNode(true, $this->siteNode);
        $this->service->method('findMemberAreaRootNodeForAccount')->willReturn($memberAreaRoot);

        self::assertSame($memberAreaRoot, $this->service->getPortalRootNode(new Account()));
    }

    /**
     * @test
     */
    public function siteNodeIsUsedWithoutPortalRoot(): void
    {
        $memberAreaRoot = $this->createNode(false, $this->createNode(false, $this->siteNode));
        $this->service->method('findMemberAreaRootNodeForAccount')->willReturn($memberAreaRoot);

        self::assertSame($this->siteNode, $this->service->getPortalRootNode(new Account()));
    }

    /**
     * @test
     */
    public function siteNodeIsUsedWithoutMemberAreaRoot(): void
    {
        $this->service->method('findMemberAreaRootNodeForAccount')->willReturn(null);

        self::assertSame($this->siteNode, $this->service->getPortalRootNode(new Account()));
    }

    /**
     * @return NodeInterface|MockObject
     */
    protected function createNode(bool $isPortalRoot, ?NodeInterface $parent)
    {
        $nodeType = $this->createMock(NodeType::class);
        $nodeType->method('isOfType')->willReturnCallback(function (string $nodeTypeName) use ($isPortalRoot) {
            return $isPortalRoot && $nodeTypeName === MemberAreaService::PORTALROOT_NODETYPE_NAME;
        });

        $node = $this->createMock(NodeInterface::class);
        $node->method('getNodeType')->willReturn($nodeType);
        $node->method('getParent')->willReturn($parent);

        return $node;
    }
}
