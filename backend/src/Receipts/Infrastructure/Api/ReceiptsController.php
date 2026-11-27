<?php

declare(strict_types=1);

namespace App\Receipts\Infrastructure\Api;

use App\Receipts\Application\GetListReceipts\GetListReceiptsHandler;
use App\Receipts\Application\GetListReceipts\GetListReceiptsQuery;
use App\Receipts\Application\GetListSpending\GetListSpendingHandler;
use App\Receipts\Application\GetListSpending\GetListSpendingQuery;
use App\Receipts\Application\GetReceipt\GetReceiptHandler;
use App\Receipts\Application\GetReceipt\GetReceiptQuery;
use App\Receipts\Application\UploadReceipt\InvalidReceiptPhoto;
use App\Receipts\Application\UploadReceipt\UploadReceiptCommand;
use App\Receipts\Application\UploadReceipt\UploadReceiptHandler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Receipts of a list (PD-63): uploaded, read and reviewed by its owner and members.
 */
#[AsController]
final readonly class ReceiptsController
{
    public function __construct(
        private UploadReceiptHandler $uploadReceipt,
        private GetListReceiptsHandler $listReceipts,
        private GetListSpendingHandler $listSpending,
        private GetReceiptHandler $getReceipt,
    ) {
    }

    #[Route('/api/lists/{id}/receipts', name: 'api_lists_receipts_list', methods: ['GET'])]
    public function list(#[CurrentUser] UserInterface $actor, string $id): JsonResponse
    {
        return new JsonResponse(ReceiptJson::list(($this->listReceipts)(new GetListReceiptsQuery($actor->getUserIdentifier(), $id))));
    }

    #[Route('/api/lists/{id}/spending', name: 'api_lists_spending', methods: ['GET'])]
    public function spending(#[CurrentUser] UserInterface $actor, string $id): JsonResponse
    {
        return new JsonResponse(ReceiptJson::spending(($this->listSpending)(new GetListSpendingQuery($actor->getUserIdentifier(), $id))));
    }

    #[Route('/api/lists/{id}/receipts/{receiptId}', name: 'api_lists_receipts_get', methods: ['GET'])]
    public function get(#[CurrentUser] UserInterface $actor, string $id, string $receiptId): JsonResponse
    {
        return new JsonResponse(ReceiptJson::receipt(($this->getReceipt)(new GetReceiptQuery($actor->getUserIdentifier(), $id, $receiptId))));
    }

    /**
     * Multipart: "supermarketId" and the "photo" file. It answers before the photo is read (PD-65).
     */
    #[Route('/api/lists/{id}/receipts', name: 'api_lists_receipts_upload', methods: ['POST'])]
    public function upload(#[CurrentUser] UserInterface $actor, string $id, Request $request): JsonResponse
    {
        $receiptId = ($this->uploadReceipt)(new UploadReceiptCommand(
            $actor->getUserIdentifier(),
            $id,
            $request->request->getString('supermarketId'),
            self::photoOf($request),
        ));

        return new JsonResponse(['id' => $receiptId], Response::HTTP_ACCEPTED);
    }

    private static function photoOf(Request $request): string
    {
        $photo = $request->files->get('photo');
        if (!$photo instanceof UploadedFile) {
            throw InvalidReceiptRequest::photoNotUploaded();
        }
        if (\in_array($photo->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
            throw InvalidReceiptPhoto::tooBig(UploadReceiptHandler::MAX_PHOTO_BYTES);
        }
        if (!$photo->isValid()) {
            throw InvalidReceiptRequest::photoNotUploaded();
        }

        $contents = file_get_contents($photo->getPathname());

        return false === $contents ? throw InvalidReceiptRequest::photoNotUploaded() : $contents;
    }
}
