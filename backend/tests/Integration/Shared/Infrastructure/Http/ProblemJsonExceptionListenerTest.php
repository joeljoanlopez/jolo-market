<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\Conflict;
use App\Shared\Domain\Exception\InvalidArgument;
use App\Shared\Domain\Exception\NotFound;
use App\Shared\Infrastructure\Http\ProblemJsonExceptionListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ProblemJsonExceptionListenerTest extends TestCase
{
    #[Test]
    public function it_maps_a_not_found_domain_error_to_404_with_its_message_as_detail(): void
    {
        $response = $this->handle(new SampleShoppingListNotFound('Shopping list "42" was not found.'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404, 'detail' => 'Shopping list "42" was not found.'],
            $this->body($response),
        );
    }

    #[Test]
    public function it_maps_an_invalid_argument_domain_error_to_422(): void
    {
        $response = $this->handle(new SampleInvalidQuantity('Quantity must be positive.'));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Unprocessable Content', 'status' => 422, 'detail' => 'Quantity must be positive.'],
            $this->body($response),
        );
    }

    #[Test]
    public function it_maps_a_conflict_domain_error_to_409(): void
    {
        $response = $this->handle(new SampleUsernameTaken('Username "maria" is already taken.'));

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Conflict', 'status' => 409, 'detail' => 'Username "maria" is already taken.'],
            $this->body($response),
        );
    }

    #[Test]
    public function it_maps_any_other_exception_to_a_500_without_internal_details(): void
    {
        $response = $this->handle(new \RuntimeException('SQLSTATE[HY000]: secret connection details'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500],
            $this->body($response),
        );
    }

    private function handle(\Throwable $exception): Response
    {
        $event = new ExceptionEvent(
            self::createStub(HttpKernelInterface::class),
            Request::create('/api/anything'),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        (new ProblemJsonExceptionListener())($event);

        $response = $event->getResponse();
        self::assertNotNull($response);

        return $response;
    }

    /**
     * @return array<mixed>
     */
    private function body(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}

final class SampleShoppingListNotFound extends NotFound
{
}

final class SampleInvalidQuantity extends InvalidArgument
{
}

final class SampleUsernameTaken extends Conflict
{
}
