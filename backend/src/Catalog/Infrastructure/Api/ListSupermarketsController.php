<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api;

use App\Catalog\Application\ListSupermarkets\ListSupermarketsHandler;
use App\Catalog\Application\ListSupermarkets\ListSupermarketsQuery;
use App\Catalog\Application\ListSupermarkets\SupermarketSummary;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ListSupermarketsController
{
    public function __construct(private ListSupermarketsHandler $listSupermarkets)
    {
    }

    #[Route('/api/supermarkets', name: 'api_supermarkets_list', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn (SupermarketSummary $supermarket): array => ['id' => $supermarket->id, 'name' => $supermarket->name],
            ($this->listSupermarkets)(new ListSupermarketsQuery()),
        ));
    }
}
