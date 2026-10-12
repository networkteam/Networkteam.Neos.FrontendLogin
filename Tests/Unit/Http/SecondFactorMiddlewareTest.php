<?php
namespace Networkteam\Neos\FrontendLogin\Tests\Unit\Http;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Authentication\TokenInterface;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Tests\UnitTestCase;
use Networkteam\Neos\FrontendLogin\Http\SecondFactorMiddleware;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorService;
use Networkteam\Neos\FrontendLogin\Security\SecondFactor\SecondFactorSessionStorage;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SecondFactorMiddlewareTest extends UnitTestCase
{
    const PROVIDER_NAME = 'Networkteam.Neos.FrontendLogin:Frontend';

    protected SecondFactorMiddleware $middleware;

    /**
     * @var SecurityContext|MockObject
     */
    protected $securityContext;

    /**
     * @var SecondFactorService|MockObject
     */
    protected $secondFactorService;

    /**
     * @var SecondFactorSessionStorage|MockObject
     */
    protected $sessionStorage;

    protected Account $account;

    protected RequestHandlerInterface $handler;

    protected bool $handlerCalled = false;

    protected function setUp(): void
    {
        $this->securityContext = $this->createMock(SecurityContext::class);
        $this->securityContext->method('canBeInitialized')->willReturn(true);
        $this->secondFactorService = $this->createMock(SecondFactorService::class);
        $this->secondFactorService->method('getCodeFormUri')->willReturn('/frontend-login/second-factor');
        $this->sessionStorage = $this->createMock(SecondFactorSessionStorage::class);

        $this->middleware = new SecondFactorMiddleware();
        $this->inject($this->middleware, 'securityContext', $this->securityContext);
        $this->inject($this->middleware, 'secondFactorService', $this->secondFactorService);
        $this->inject($this->middleware, 'sessionStorage', $this->sessionStorage);
        $this->inject($this->middleware, 'authenticationProviderName', self::PROVIDER_NAME);

        $this->account = new Account();

        $this->handler = new class($this) implements RequestHandlerInterface {
            private $test;

            public function __construct($test)
            {
                $this->test = $test;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->test->markHandlerCalled();
                return new Response(200);
            }
        };
    }

    public function markHandlerCalled(): void
    {
        $this->handlerCalled = true;
    }

    /**
     * @test
     */
    public function passesThroughWithoutFrontendToken(): void
    {
        $this->securityContext->method('getAuthenticationTokens')->willReturn([]);
        $this->secondFactorService->expects(self::never())->method('isRequired');

        $this->assertPassesThrough($this->createRequest('/neos/content'));
    }

    /**
     * @test
     */
    public function passesThroughWithUnauthenticatedFrontendToken(): void
    {
        $this->givenFrontendToken(false);
        $this->secondFactorService->expects(self::never())->method('isRequired');

        $this->assertPassesThrough($this->createRequest('/member-area'));
    }

    /**
     * @test
     */
    public function passesThroughIfSecondFactorIsNotRequired(): void
    {
        $this->givenFrontendToken(true);
        $this->secondFactorService->method('isRequired')->willReturn(false);

        $this->assertPassesThrough($this->createRequest('/member-area'));
    }

    /**
     * @test
     */
    public function passesThroughIfSecondFactorIsAuthenticated(): void
    {
        $this->givenFrontendToken(true);
        $this->secondFactorService->method('isRequired')->willReturn(true);
        $this->sessionStorage->method('getStatus')->willReturn(SecondFactorSessionStorage::STATUS_AUTHENTICATED);

        $this->assertPassesThrough($this->createRequest('/member-area'));
    }

    public static function allowedActionsProvider(): array
    {
        return [
            'code form' => ['secondFactor'],
            'verify' => ['verifySecondFactor'],
            'resend' => ['resendSecondFactor'],
            'logout' => ['logout'],
        ];
    }

    /**
     * @test
     * @dataProvider allowedActionsProvider
     */
    public function allowsCodeFormActionsWhilePending(string $action): void
    {
        $this->givenPendingChallenge();

        $this->assertPassesThrough($this->createRequest('/frontend-login/x', $this->frontendLoginRoute($action)));
    }

    public static function blockedRequestsProvider(): array
    {
        return [
            'other page' => [['@package' => 'Neos.Neos', '@controller' => 'Frontend\\Node', '@action' => 'show']],
            'authenticate action' => [['@package' => 'Networkteam.Neos.FrontendLogin', '@controller' => 'Authentication', '@action' => 'authenticate']],
            'other package action with same name' => [['@package' => 'Acme.Shop', '@controller' => 'Authentication', '@action' => 'secondFactor']],
            'unrouted' => [[]],
        ];
    }

    /**
     * @test
     * @dataProvider blockedRequestsProvider
     */
    public function redirectsEverythingElseToCodeFormWhilePending(array $routingResults): void
    {
        $this->givenPendingChallenge();

        $response = $this->middleware->process($this->createRequest('/member-area', $routingResults), $this->handler);

        self::assertFalse($this->handlerCalled);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/frontend-login/second-factor', $response->getHeaderLine('Location'));
    }

    /**
     * @test
     */
    public function fallbackStartsChallengeWithRequestUriAndRedirects(): void
    {
        $this->givenFrontendToken(true);
        $this->secondFactorService->method('isRequired')->willReturn(true);
        $this->sessionStorage->method('getStatus')->willReturn(null);
        $this->secondFactorService->expects(self::once())->method('startChallenge')
            ->with($this->account, 'https://example.com/member-area?page=2')
            ->willReturn(true);

        $response = $this->middleware->process($this->createRequest('/member-area?page=2'), $this->handler);

        self::assertFalse($this->handlerCalled);
        self::assertSame(303, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function fallbackPassesThroughOnRememberedBrowser(): void
    {
        $this->givenFrontendToken(true);
        $this->secondFactorService->method('isRequired')->willReturn(true);
        $this->sessionStorage->method('getStatus')->willReturn(null);
        $this->secondFactorService->expects(self::once())->method('startChallenge')->willReturn(false);

        $this->assertPassesThrough($this->createRequest('/member-area'));
    }

    /**
     * @test
     */
    public function fallbackDoesNotStartChallengeForLogout(): void
    {
        $this->givenFrontendToken(true);
        $this->secondFactorService->method('isRequired')->willReturn(true);
        $this->sessionStorage->method('getStatus')->willReturn(null);
        $this->secondFactorService->expects(self::never())->method('startChallenge');

        $this->assertPassesThrough($this->createRequest('/frontend-login/logout.html', $this->frontendLoginRoute('logout')));
    }

    protected function givenPendingChallenge(): void
    {
        $this->givenFrontendToken(true);
        $this->secondFactorService->method('isRequired')->willReturn(true);
        $this->sessionStorage->method('getStatus')->willReturn(SecondFactorSessionStorage::STATUS_NEEDED);
        $this->secondFactorService->expects(self::never())->method('startChallenge');
    }

    protected function givenFrontendToken(bool $authenticated): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('isAuthenticated')->willReturn($authenticated);
        $token->method('getAccount')->willReturn($this->account);
        $this->securityContext->method('getAuthenticationTokens')->willReturn([self::PROVIDER_NAME => $token]);
    }

    protected function frontendLoginRoute(string $action): array
    {
        return ['@package' => 'Networkteam.Neos.FrontendLogin', '@controller' => 'Authentication', '@action' => $action];
    }

    protected function createRequest(string $path, array $routingResults = []): ServerRequestInterface
    {
        return (new ServerRequest('GET', 'https://example.com' . $path))
            ->withAttribute(ServerRequestAttributes::ROUTING_RESULTS, $routingResults);
    }

    protected function assertPassesThrough(ServerRequestInterface $request): void
    {
        $response = $this->middleware->process($request, $this->handler);

        self::assertTrue($this->handlerCalled);
        self::assertSame(200, $response->getStatusCode());
    }
}
