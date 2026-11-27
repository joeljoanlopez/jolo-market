# E10 — Receipts and spending

**Goal:** the owner and the members of a shopping list upload the photo of a Mercadona, Bonpreu i Esclat or Aldi receipt. The server reads it with the instance's local AI, or with local OCR when the AI is not configured or fails, and a draft comes out of it. They review the draft, link each line to a product of that supermarket and confirm it. The list then shows what was bought and how much was spent each month, per supermarket. Lines that were linked once are linked by themselves next time.

Related decisions: PD-7, PD-15, PD-19, PD-22, PD-24, PD-42, PD-43, PD-44, PD-62 to PD-71, PD-112 to PD-114, PD-127 to PD-130. Open questions: none.

## Acceptance criteria

- The owner or a member of a list uploads one JPEG, PNG or WebP photo of up to 10 MB and picks one of the enabled supermarkets. Another file type, a bigger file or a supermarket that is not enabled is rejected with a message (PD-64, PD-68).
- The upload answers at once and the receipt shows as "reading". Within the list's polling it becomes a draft with the date, the total and the lines read from the photo. When reading fails, it becomes an empty draft (PD-65).
- In the review the user sees the photo and can edit the date and the total, and can add, edit and remove product lines (text, quantity in units or kg, amount) and discount lines (PD-66).
- Each product line shows the product the memory knows for its text or a suggested one, from the receipt's supermarket only. The user can accept it, pick another one through the search, or leave the line unlinked (PD-67).
- When products minus discounts differ from the total, the review warns about it, but the receipt can still be confirmed. It cannot be confirmed without a date and a total, and the date cannot be in the future (PD-66).
- After a receipt is confirmed, a new receipt of the same supermarket in any list links the same printed text to the same product by itself. The last confirmation wins (PD-67). A product line added by hand, or whose text changes, is linked by the memory or gets a suggestion too (PD-128).
- The list shows its confirmed receipts (date, supermarket, total, uploader), newest first, and the total per month with its breakdown per supermarket. Drafts and receipts still being read are shown apart and do not count (PD-70).
- Any member can edit a confirmed receipt again and confirm it again, which stores the memory again (PD-130). Any member can delete a receipt in any status, also while it is being read, after confirming (PD-129). Deleting a receipt or the list removes its photo (PD-63, PD-65).
- Users who are not owner or member get `404` for the receipts, the spending and the photos of the list (PD-63).
- Confirming a receipt changes neither the list items nor the catalog prices and price history (PD-67, PD-71).
- `deploy/backup.sh` also saves the receipt photos, and the README explains how to restore them (PD-69).
- When `RECEIPT_AI_BASE_URL` is set, the receipt is read by the AI. When the AI is not configured, cannot be reached, times out, answers with an error or returns JSON that does not match the schema, the receipt is read by the OCR instead. The receipt stores `readBy` (`ai`, `ocr` or `none`) (PD-112, PD-113, PD-114).
- No test reaches the network. The AI reader is tested against recorded AI answers, and OCR and parsers against recorded photos (PD-62, PD-112).

## Tasks

### Spike

- [ ] E10.1 Spike (one day): run Tesseract (`spa` + `cat`) on the sample receipts of each chain, choose the image preprocessing (grayscale, threshold, scaling) and check the text is good enough for a parser. Record what each chain prints (date, total, line, weight line, discount line) and the preprocessing in a note under this task.
  - Needs **2–3 real receipt photos per chain** from the product owner, with the card digits covered. They become the fixtures in `backend/tests/Fixtures/Receipts/`.
  - Note: not done yet (PD-127): no real receipt photos or access to the local AI yet. ❓ Needs the photos and the model.
- [ ] E10.40 Spike (one day): send the same sample photos to the product owner's local vision model through its OpenAI-compatible API with `response_format` `json_schema`. Write the JSON schema and the prompt (supermarket name, Spanish and Catalan receipts, amounts in cents, units or kg). Check whether the server honours `json_schema` and how long a receipt takes, and choose the image size sent. Record the model, server, schema, prompt, timings and the answers in a note under this task. The answers become the recorded fixtures of E10.41 (PD-112).
  - Needs the product owner's model name and server (Ollama, llama.cpp, vLLM, LM Studio…) to run it.
  - Note: not done yet (PD-127): no real receipt photos or access to the local AI yet. ❓ Needs the photos and the model.

### Domain (Receipts)

- [x] E10.2 Create the `Receipts` context and add its value objects: `ReceiptId`, `ReceiptStatus` (`READING`, `DRAFT`, `CONFIRMED`), `LineText` (1–120 characters after trimming), `LineQuantity` (whole units 1–999, or a weight of 1–99 999 thousandths of a kg) `ReadBy` (`AI`, `OCR`, `NONE`, PD-114) and `ReceiptDate` (a day; "not in the future" is checked against the `Clock` where it is set) (PD-66).
- [x] E10.3 Add the `Receipt` aggregate. `upload(listId, supermarketId, uploaderId, photo, uploadedAt)` starts it as `READING`. `completeReading(readResult)` moves it to `DRAFT` with the date, total and lines that were read and who read them, or empty with `readBy` `NONE` when reading failed (PD-65, PD-114).
- [x] E10.4 Add line behavior to `Receipt`:
  - `addProductLine`, `addDiscountLine`, `updateLine`, `removeLine`, `linkProduct(lineId, productId)` and `unlink` (product lines only).
  - `changeDate` and `changeTotal`.
  - At most 200 lines.
  - Every amount is a positive `Money` (PD-19). `difference()` is products − discounts − total, with its sign as a separate value (PD-66).
- [x] E10.5 Add `confirm` to `Receipt`. It needs a date and a total, else `ReceiptNotConfirmable` (422). A draft becomes `CONFIRMED`. Editing a confirmed receipt keeps it confirmed. A receipt that is still `READING` cannot be edited or confirmed (409) (PD-65).
- [x] E10.6 Add the ports `ReceiptRepository` (`save`, `findById`, `findByList`, `removeByList`), `ReceiptPhotoStorage` (`store`, `read`, `delete`) and `ProductLinkMemory` (`find(supermarketId, text)`, `remember(supermarketId, text, productId)` where the last one wins). Text is compared ignoring case and repeated spaces (PD-67).

### Catalog

- [x] E10.7 Add a Catalog query API `SuggestProductsHandler`: a receipt text and a supermarket give its most similar products, available or not. Similarity is the number of query words that match the start of a word in the name or brand (`ENT.` → "Entera", `HACEND` → "Hacendado"), and ties are broken by name. Unit tests cover abbreviated words, punctuation, accents and an empty result (PD-67).

### ShoppingList

- [x] E10.8 Add a ShoppingList query API `CheckListAccessHandler`: list id + actor → allowed or `ShoppingListNotFound` (404), with the same rule as `GetList` (owner or member, PD-63).
- [ ] E10.9 Make `DeleteList` publish a `ShoppingListDeleted` application event through a new `ShoppingListEvents` port, after the list is removed. ShoppingList knows nothing about receipts (PD-44, PD-63).

### Application (Receipts)

- [x] E10.10 Add the Receipts ports `ListAccess` (on E10.8), `ReceiptProductCatalog` (suggestions from E10.7 and products by id through `GetProductsHandler`), `ReceiptUserDirectory` (uploader usernames), `ReceiptReader` (photo → read result for a supermarket with its `readBy`, or a failure) and `ReceiptReadingQueue` (asks for a receipt to be read later), each with an in-memory fake.
  - Note: a sixth port, `ReceiptSupermarkets`, tells whether a supermarket is enabled (PD-64) and gives the supermarket names, through Catalog. The reader returns plain read data (`ReadReceiptData`), which `ReadReceipt` turns into lines with ids.
- [x] E10.11 Add `UploadReceipt`, which receives an explicit `actorId`. It rejects a non-member (404), a supermarket that is not enabled (422, PD-64), and a file that is not JPEG, PNG or WebP or that is over 10 MB (422, PD-68). It then stores the photo, saves the receipt as `READING` and queues the reading.
- [x] E10.12 Add `ReadReceipt`, run by the worker. It reads the photo and completes the reading. A product line whose text the memory knows gets that product linked. Every other product line gets the first suggestion as its **suggested** product, which is not linked until accepted. A reader failure gives an empty draft (PD-65, PD-67).
  - Note: a receipt deleted before the worker reads it is skipped (PD-129).
- [x] E10.13 Add the editing use cases, each with an explicit `actorId` and the access check:
  - `UpdateReceipt` (date, total).
  - `AddLine`, `UpdateLine`, `RemoveLine`.
  - `LinkLine` and `UnlinkLine`. Linking checks that the product belongs to the receipt's supermarket, else 422.
  - `DeleteReceipt`, which also deletes the photo.

  Unknown receipt or line, or a receipt of another list → 404.
  - Note: `AddLine` and a text change in `UpdateLine` apply the memory or the suggestion like `ReadReceipt` (PD-128). `DeleteReceipt` works in any status, also `READING` (PD-129).
- [x] E10.14 Add `ConfirmReceipt`: it confirms the receipt and stores "text → product" in the memory for every linked line of that supermarket, so the last confirmation wins. It never touches list items or the catalog (PD-67, PD-71).
  - Note: a confirmed receipt can be confirmed again, which stores the memory again (PD-130).
- [x] E10.15 Add the queries:
  - `GetReceipt`: status, supermarket, date, total, uploader, and lines with the linked or suggested product (name, brand, available). A product the catalog no longer knows is shown by its text. It also returns `differenceCents`.
  - `GetListReceipts`: `pending` (reading and draft) and `confirmed`, newest first.
  - `GetListSpending`: confirmed receipts only. It gives the total per month (by receipt date, Europe/Madrid) and per supermarket, newest month first (PD-70).
  - Note: pending receipts go by upload time, newest first; confirmed ones by receipt date, then upload time. The supermarkets of a month go by name. Receipts carry the supermarket name and the upload time; `readBy` is not returned (PD-114).
- [x] E10.16 Handle `ShoppingListDeleted` in Receipts: remove every receipt of the list and its photos (PD-63).
  - Note: the use case is `RemoveListReceipts`; the Messenger handler of `ShoppingListDeleted` that calls it is wired with E10.9 and E10.23.

### Infrastructure

- [ ] E10.17 Install Tesseract with the `spa` and `cat` data in the php image (dev and prod), pinned like the other versions, and record it in "Pinned versions". Add `TesseractReceiptOcr` with the preprocessing from E10.1. An integration test reads a fixture photo inside the container.
  - Note: not done yet (PD-127): no real receipt photos or access to the local AI yet. ❓ Needs the photos and the model.
- [ ] E10.18 Add `MercadonaReceiptParser` (OCR text → date, total, product lines with units or weight, discount lines). Unit tests run against the recorded OCR text of every Mercadona fixture, and unreadable parts are left out rather than guessed (PD-62).
  - Note: not done yet (PD-127): no real receipt photos or access to the local AI yet. ❓ Needs the photos and the model.
- [ ] E10.19 Add `BonpreuEsclatReceiptParser`, the same way (PD-62).
  - Note: not done yet (PD-127): no real receipt photos or access to the local AI yet. ❓ Needs the photos and the model.
- [ ] E10.20 Add `AldiReceiptParser`, the same way (PD-62).
  - Note: not done yet (PD-127): no real receipt photos or access to the local AI yet. ❓ Needs the photos and the model.
- [ ] E10.41 Add `OpenAiCompatibleReceiptAi` over `symfony/http-client`: it posts the photo (base64 data URL, sized as in E10.40), the supermarket, the prompt and the JSON schema of E10.40 to `RECEIPT_AI_BASE_URL/chat/completions` with `RECEIPT_AI_MODEL`, the optional Bearer `RECEIPT_AI_API_KEY` and `RECEIPT_AI_TIMEOUT_SECONDS`. It maps the answer to a read result, and treats a network error, a timeout, a non-2xx answer, or JSON that does not match the schema or the receipt rules (PD-66) as a failure. One attempt, no retry. Unit tests use `MockHttpClient` with the recorded answers of E10.40 and cover each failure (PD-112, PD-113).
  - Note: not done yet (PD-127): no real receipt photos or access to the local AI yet. ❓ Needs the photos and the model.
- [ ] E10.21 Add the `ReceiptReader` adapter, which reads with the AI first and the OCR as fallback (PD-112):
  - When `RECEIPT_AI_BASE_URL` is set, it asks E10.41 and, on success, returns its result with `readBy` `AI`.
  - When the AI is not configured or fails, it runs the OCR and the parser of the receipt's supermarket, and returns `readBy` `OCR`.
  - When both fail, it is a failure (`readBy` `NONE`).
  - Every failure and the `readBy` are logged without the receipt text (PD-114). Unit tests use fakes of the AI and the OCR for every path.
  - Note: not done yet (PD-127): no real receipt photos or access to the local AI yet. ❓ Needs the photos and the model.
- [x] E10.22 Add `FilesystemReceiptPhotoStorage` under `RECEIPT_STORAGE_DIR` (one file per receipt id, never a name taken from the upload). Add a `receipts` volume to `compose.yaml` and `compose.prod.yaml`. Integration test against a temporary directory.
- [x] E10.23 Add `symfony/messenger` with the Doctrine transport for `ReceiptReadingQueue` and the `ShoppingListDeleted` event, and a `worker` service (`messenger:consume`) in dev and prod. Every new development command or script gets the `make` question of PD-61.
  - Note: the `receipts` transport is `doctrine://default?queue_name=receipts`, with three retries and a `failed` transport; `messenger_messages` is created by a migration (no `auto_setup`). Tests use `in-memory://`. No new development command was added, so there was no `make` question. Until E10.21, the `ReceiptReader` is `NoReceiptReader`, which always fails (PD-127).
- [x] E10.24 Add the migrations and the DBAL adapters:
  - Tables `receipt` (with `read_by`), `receipt_line` and `receipt_product_link` (PK `supermarket_id`, `normalized_text`).
  - `DbalReceiptRepository`, `DbalProductLinkMemory` and the spending read model.
  - Integration tests.
- [x] E10.25 Add the adapters of the cross-context ports (`ShoppingListListAccess`, `CatalogReceiptProductCatalog`, `IdentityReceiptUserDirectory`). Add the Receipts layers and the allowed paths to `deptrac.yaml`: Receipts Infrastructure → ShoppingList Application (`CheckListAccessHandler`, `ShoppingListDeleted`), Catalog Application and Identity Application. No exceptions.
  - Note: `CatalogReceiptSupermarkets` (the port of E10.10) reads the enabled supermarkets through `ListSupermarketsHandler`; a supermarket disabled later shows its id instead of its name.

### API

- [ ] E10.26 Add `POST /api/lists/{id}/receipts` (multipart: `supermarketId`, `photo`) → `202 {id}`. Raise the nginx body limit to fit 10 MB. Functional tests: non-member `404`, wrong type, size or supermarket `422`, missing CSRF header `403`.
- [ ] E10.27 Add `GET /api/lists/{id}/receipts` (`{pending, confirmed}`), `GET /api/lists/{id}/spending` and `GET /api/lists/{id}/receipts/{receiptId}`, with functional tests.
- [ ] E10.28 Add `GET /api/lists/{id}/receipts/{receiptId}/photo`, which streams the stored image with its type and `Cache-Control: private`, members only (`404` otherwise). Functional tests.
- [ ] E10.29 Add the editing endpoints, with functional tests (`404` for others, `409` while reading, `422` on invalid data):
  - `PATCH /api/lists/{id}/receipts/{receiptId}` (`{date, totalCents}`).
  - `POST …/lines`, `PATCH …/lines/{lineId}` and `DELETE …/lines/{lineId}`.
  - `PUT …/lines/{lineId}/product` (`{productId}`) and `DELETE …/lines/{lineId}/product`.
  - `POST …/confirm` and `DELETE /api/lists/{id}/receipts/{receiptId}`.

### Platform

- [ ] E10.30 Make `deploy/backup.sh` also archive the photo volume as `jolo-market-<UTC timestamp>-receipts.tar.gz` next to the dump. bats tests cover the new file and a failure of either part (PD-51, PD-69).

### Frontend

- [ ] E10.31 Add the `receipts` feature domain: types (`Receipt`, `ReceiptLine`, `ReceiptStatus`, `MonthlySpending`) and pure functions `formatLineQuantity` (`2 ud`, `0,532 kg`), `formatMonth` (`noviembre 2026`) and `hasDifference`.
- [ ] E10.32 Add `FormData` uploads to `createHttpClient` (no JSON `Content-Type`, with the CSRF header). Add `createReceiptsApi(httpClient)` over the E10.26–E10.29 endpoints, validating every body (`UnexpectedResponseError` otherwise). MSW tests.
- [ ] E10.33 Add the `ReceiptsApi` port with its context and in-memory fake. Add the hooks `useListReceipts` and `useListSpending`, which poll every 5 s while a receipt is `reading` (PD-7), `useReceipt` and the editing mutations.
- [ ] E10.34 Add the receipts section to the list page:
  - `UploadReceiptForm`: supermarket select among the enabled ones, and a photo input that accepts only JPEG, PNG and WebP up to 10 MB, checked before upload.
  - `PendingReceiptsList` ("Leyendo…" and "Borrador", linking to the review).
  - `ConfirmedReceiptsList`.
  - `MonthlySpending` with its per-supermarket breakdown, using the supermarket color tokens (PD-20).
  - Empty, loading and error states.
- [ ] E10.35 Add the review page `/lists/:id/receipts/:receiptId`:
  - The photo, and the date and total fields.
  - The line editor: product and discount lines, quantity in units or kg.
  - On each line, the linked or suggested product with "Aceptar", "Cambiar" (search limited to the receipt's supermarket, PD-33) and "Sin enlazar".
  - The difference warning and "Confirmar".
  - "Borrar" with the inline confirmation of PD-42.
  - A non-member gets the not-found page.
- [ ] E10.36 Add all the Spanish copy of the feature to `es.json` (no hard-coded strings).

### End to end

- [ ] E10.37 Playwright journey:
  1. In a list, upload a fixture receipt photo.
  2. See "Leyendo…" turn into a draft.
  3. Fix a line, accept a suggestion, link another line through the search, and confirm.
  4. See the receipt and the month's spending in the list.
  5. Upload the same photo again and see the line linked by the memory.
  - Note: not done yet (PD-127): no real receipt photos or access to the local AI yet. ❓ Needs the photos and the model.

### Docs

- [ ] E10.38 Update `docs/architecture.md` with the Receipts context:
  - Bounded contexts, the domain model, ports and adapters, and the allowed cross-context paths.
  - The worker and the optional local AI in "Runtime and deployment".
  - The receipt flow in "Key flows", with the AI first and the OCR fallback (PD-112).
  - The HTTP API, and the tables in "Persistence".
- [ ] E10.39 Update the README:
  - Receipts API.
  - `RECEIPT_STORAGE_DIR` and the `receipts` volume in the `.env` reference.
  - `RECEIPT_AI_BASE_URL`, `RECEIPT_AI_MODEL`, `RECEIPT_AI_API_KEY` and `RECEIPT_AI_TIMEOUT_SECONDS`, how to point them at Ollama or another OpenAI-compatible server, and the warning that an AI not run by the operator sends the receipt photos out of the server (PD-113).
  - The `worker` service.
  - Backup and restore of the photos (PD-69).
