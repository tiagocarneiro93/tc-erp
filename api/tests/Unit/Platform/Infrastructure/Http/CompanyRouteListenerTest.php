<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Infrastructure\Http;

use App\Platform\Infrastructure\Http\CompanyRouteListener;
use App\Platform\Infrastructure\Security\CompanyVoter;
use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class CompanyRouteListenerTest extends TestCase
{
    public function testDoesNothingWhenTheRouteHasNoCompanyId(): void
    {
        $context = new RequestCompanyContext();
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::never())->method('isGranted');

        (new CompanyRouteListener($context, $authorizationChecker))->onKernelRequest($this->requestEvent(new Request()));

        self::assertFalse($context->hasCompany());
    }

    public function testRespondsNotFoundForAMalformedCompanyId(): void
    {
        $context = new RequestCompanyContext();
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::never())->method('isGranted');

        $request = new Request(attributes: ['companyId' => 'not-a-uuid']);
        $event = $this->requestEvent($request);

        (new CompanyRouteListener($context, $authorizationChecker))->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(404, $event->getResponse()->getStatusCode());
        self::assertFalse($context->hasCompany());
    }

    public function testRespondsNotFoundWhenTheVoterDenies(): void
    {
        $context = new RequestCompanyContext();
        $companyId = CompanyId::generate();
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->with(CompanyVoter::MEMBER, self::callback(
            static fn (CompanyId $subject) => $subject->equals($companyId),
        ))->willReturn(false);

        $event = $this->requestEvent(new Request(attributes: ['companyId' => $companyId->toString()]));

        (new CompanyRouteListener($context, $authorizationChecker))->onKernelRequest($event);

        self::assertSame(404, $event->getResponse()?->getStatusCode());
        self::assertFalse($context->hasCompany());
    }

    public function testSetsTheCompanyContextWhenTheVoterGrants(): void
    {
        $context = new RequestCompanyContext();
        $companyId = CompanyId::generate();
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $event = $this->requestEvent(new Request(attributes: ['companyId' => $companyId->toString()]));

        (new CompanyRouteListener($context, $authorizationChecker))->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertTrue($context->hasCompany());
        self::assertTrue($companyId->equals($context->companyId()));
    }

    private function requestEvent(Request $request): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
