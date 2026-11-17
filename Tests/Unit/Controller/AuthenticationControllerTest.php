<?php
namespace Networkteam\Neos\FrontendLogin\Tests\Unit\Controller;

use GuzzleHttp\Psr7\ServerRequest;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Tests\UnitTestCase;
use Networkteam\Neos\FrontendLogin\Controller\AuthenticationController;

class AuthenticationControllerTest extends UnitTestCase
{
    /**
     * @test
     */
    public function buildUriForRequestWorksForInterceptedRequestRestoredFromSession(): void
    {
        $interceptedRequest = $this->createActionRequest();
        $interceptedRequest->setControllerPackageKey('Neos.Neos');
        $interceptedRequest->setControllerName('Frontend\Node');
        $interceptedRequest->setControllerActionName('show');
        $interceptedRequest->setArgument('node', '/sites/site/portal@live');
        $interceptedRequest->setFormat('html');

        // The security context stores the intercepted request in the session, which drops the HTTP request
        $restoredRequest = unserialize(serialize($interceptedRequest));
        $this->injectRequestDependencies($restoredRequest);

        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->method('reset')->willReturnSelf();
        $uriBuilder->method('setCreateAbsoluteUri')->with(true)->willReturnSelf();
        $uriBuilder->method('setFormat')->with('html')->willReturnSelf();
        $uriBuilder->expects(self::once())
            ->method('uriFor')
            ->with('show', ['node' => '/sites/site/portal@live'], 'Frontend\Node', 'Neos.Neos')
            ->willReturn('http://localhost/portal.html');

        $controller = new class extends AuthenticationController {
            public function callBuildUriForRequest(ActionRequest $request): string
            {
                return $this->buildUriForRequest($request);
            }
        };
        $this->inject($controller, 'uriBuilder', $uriBuilder);

        self::assertSame('http://localhost/portal.html', $controller->callBuildUriForRequest($restoredRequest));
    }

    /**
     * @test
     */
    public function buildUriForRequestAppendsSubpackageKey(): void
    {
        $request = $this->createActionRequest();
        $request->setControllerPackageKey('Acme.Shop');
        $request->setControllerSubpackageKey('Module');
        $request->setControllerName('Order');
        $request->setControllerActionName('index');

        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->method('reset')->willReturnSelf();
        $uriBuilder->method('setCreateAbsoluteUri')->willReturnSelf();
        $uriBuilder->method('setFormat')->willReturnSelf();
        $uriBuilder->expects(self::once())
            ->method('uriFor')
            ->with('index', [], 'Order', 'Acme.Shop\Module')
            ->willReturn('http://localhost/order');

        $controller = new class extends AuthenticationController {
            public function callBuildUriForRequest(ActionRequest $request): string
            {
                return $this->buildUriForRequest($request);
            }
        };
        $this->inject($controller, 'uriBuilder', $uriBuilder);

        self::assertSame('http://localhost/order', $controller->callBuildUriForRequest($request));
    }

    protected function createActionRequest(): ActionRequest
    {
        $request = ActionRequest::fromHttpRequest(new ServerRequest('GET', 'http://localhost/portal.html'));
        $this->injectRequestDependencies($request);
        return $request;
    }

    /**
     * Flow injects these dependencies again after unserializing; here unknown object and package names are kept as is
     */
    protected function injectRequestDependencies(ActionRequest $request): void
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('getCaseSensitiveObjectName')->willReturn(false);
        $packageManager = $this->createMock(PackageManager::class);
        $packageManager->method('getCaseSensitivePackageKey')->willReturn(false);
        $this->inject($request, 'objectManager', $objectManager);
        $this->inject($request, 'packageManager', $packageManager);
    }
}
