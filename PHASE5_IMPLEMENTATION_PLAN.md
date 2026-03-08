# Phase 5 — Document Review & Annotation (Implementation Plan)

## Context

Phase 4 completed realtime document processing visibility.
The next delivery objective is a document review workspace where users can inspect the source file inline, compare it with extracted outputs, and prepare for annotation and assignment workflows.

---

## Target Outcome After Phase 5 R1

- Document detail pages expose a dedicated review workspace payload.
- PDFs can be previewed inline through an authenticated same-origin route.
- Extracted data and classification outputs are visible beside the source document.
- Existing manual review actions (`ready_for_review -> reviewed -> approved`) remain intact.
- Phase 4 Docker/runtime wiring carries forward unchanged.

---

## Decisions

1. Use `pdfjs-dist` for the Phase 5 viewer foundation.
2. Limit R1 inline preview support to PDF documents only.
3. Serve previews through a Laravel-authenticated same-origin route instead of direct S3/browser URLs.
4. Keep extracted data and classification additive to the current document detail payload.
5. Phase 5 R1 introduces no Docker or infrastructure changes.

---

## Step 1: Review Workspace Backend Contract

1. Add `documents.preview` route: `GET /documents/{document}/preview`.
2. Authorize preview requests with the existing `DocumentPolicy::view`.
3. Stream the current document file inline from S3 with `Content-Type: application/pdf` and inline disposition.
4. Update `DocumentController@show` to eager-load:
   - `matter`
   - `uploader`
   - `classification`
   - `extractedData`
5. Add `reviewWorkspace` props with:
   - `preview.url`
   - `preview.available`
   - `preview.mime_type`
   - `preview.mode`

---

## Step 2: Review Workspace Frontend Foundation

1. Add `resources/js/components/documents/PdfViewer.vue`.
2. Add `resources/js/components/documents/ExtractedDataPanel.vue`.
3. Rework `resources/js/pages/documents/Show.vue` into a two-column review workspace:
   - left: PDF viewer or unsupported-preview fallback
   - right: extracted data, classification, metadata, activity, current review actions
4. Keep the Phase 2.6 document-experience visual system and extend it without introducing a parallel theme.

---

## Step 3: Review Data Presentation

1. Extend TypeScript document models with:
   - `DocumentExtractedData`
   - `DocumentPreviewState`
   - `DocumentReviewWorkspace`
2. Preserve existing classification payload handling for realtime updates.
3. Refresh the document snapshot when a terminal review-stage status arrives and either classification or extracted data is still missing locally.

---

## Step 4: Tests and Regression Safety

1. Add feature coverage for the review workspace page contract.
2. Add preview route authorization coverage for same-tenant and cross-tenant access.
3. Add assertions for PDF and unsupported-preview modes.
4. Keep existing document review and broadcast tests green.

---

## Test Matrix

| Scenario | Expected Result |
| --- | --- |
| Tenant user opens PDF document show page | Review workspace payload includes preview URL and PDF mode |
| Tenant user opens non-PDF document show page | Review workspace payload renders unsupported-preview mode safely |
| Authorized tenant user requests preview route | Inline PDF stream is returned |
| Cross-tenant preview request | Route is denied by tenant scoping |
| Document with extracted data and classification | Right-side review panel shows both datasets |
| Realtime status update reaches terminal review state while detail page is open | Snapshot refresh preserves extracted/classification completeness |

---

## Runtime Note

- Phase 5 R1 does not change Docker, Reverb, RabbitMQ, or worker topology.
- Continue using the Phase 4 runtime setup as-is.

---

## Final Verification

```bash
# Backend formatting
docker compose exec app vendor/bin/pint --dirty --format agent

# Frontend lint + format
npx eslint . --fix
npx prettier --write resources/

# Focused tests
docker compose exec app php artisan test --compact tests/Feature/Document/DocumentCrudTest.php
docker compose exec app php artisan test --compact tests/Feature/Tenancy/TenantScopingTest.php
docker compose exec app php artisan test --compact tests/Feature/Document/DocumentStatusBroadcastTest.php

# Full backend tests
docker compose exec app php artisan test --compact

# Frontend build
npx vite build
```

---

## Continuation Rule

After R1 merges, proceed to R2 for annotation persistence and inline review markups.
