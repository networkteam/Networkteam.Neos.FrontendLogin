<?php
namespace Networkteam\Neos\FrontendLogin\Tests\Unit\Security\SecondFactor;

use GuzzleHttp\Psr7\ServerRequest;
use Neos\Flow\Security\Account;
use Neos\Flow\Tests\UnitTestCase;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\RememberedBrowserService;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorProviderInterface;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorService;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorSessionStorage;
use PHPUnit\Framework\MockObject\MockObject;

class SecondFactorServiceTest extends UnitTestCase
{
    protected SecondFactorService $service;

    /**
     * @var SecondFactorProviderInterface|MockObject
     */
    protected $provider;

    /**
     * @var SecondFactorSessionStorage|MockObject
     */
    protected $sessionStorage;

    /**
     * @var RememberedBrowserService|MockObject
     */
    protected $rememberedBrowserService;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(SecondFactorProviderInterface::class);
        $this->sessionStorage = $this->createMock(SecondFactorSessionStorage::class);
        $this->rememberedBrowserService = $this->createMock(RememberedBrowserService::class);

        $this->service = new SecondFactorService();
        $this->inject($this->service, 'secondFactorProvider', $this->provider);
        $this->inject($this->service, 'sessionStorage', $this->sessionStorage);
        $this->inject($this->service, 'rememberedBrowserService', $this->rememberedBrowserService);
    }

    /**
     * @test
     */
    public function startChallengeStoresStateAndSendsCodeOnce(): void
    {
        $account = new Account();
        $request = (new ServerRequest('POST', 'https://example.com/frontend-login/authenticate'))->withCookieParams(['a' => 'b']);
        $this->rememberedBrowserService->method('isRemembered')->with($account, ['a' => 'b'])->willReturn(false);

        $this->sessionStorage->expects(self::once())->method('startChallenge')->with($account, '/member-area');
        $this->sessionStorage->expects(self::never())->method('markAuthenticated');
        $this->provider->expects(self::once())->method('sendCode')->with($account);

        self::assertTrue($this->service->startChallenge($account, '/member-area', $request));
    }

    /**
     * @test
     */
    public function startChallengeSkipsCodeOnRememberedBrowser(): void
    {
        $account = new Account();
        $this->rememberedBrowserService->method('isRemembered')->willReturn(true);

        $this->sessionStorage->expects(self::never())->method('startChallenge');
        $this->sessionStorage->expects(self::once())->method('markAuthenticated')->with($account);
        $this->provider->expects(self::never())->method('sendCode');

        self::assertFalse($this->service->startChallenge($account, '/member-area', new ServerRequest('POST', '/')));
    }
}
