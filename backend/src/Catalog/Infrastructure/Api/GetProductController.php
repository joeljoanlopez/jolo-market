<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api;

use App\Catalog\Application\GetProduct\GetProductHandler;
use App\Catalog\Application\GetProduct\GetProductQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class GetProductController
{
    public function __construct(private GetProductHandler $getProduct)
    {
    }

    #[Route('/api/products/{id}', name: 'api_products_get', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        $product = ($this->getProduct)(new GetProductQuery($id));

        return new JsonResponse([...ProductJson::item($product), 'available' => $product->available]);
    }
}
