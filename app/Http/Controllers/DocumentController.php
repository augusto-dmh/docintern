<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentAnnotation;
use App\Models\Matter;
use App\Models\User;
use App\Services\DocumentStatusTransitionService;
use App\Services\DocumentUploadService;
use App\Support\DocumentExperienceGuardrails;
use App\Support\DocumentReviewWorkspacePresenter;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        public DocumentUploadService $documentUploadService,
        public DocumentStatusTransitionService $documentStatusTransitionService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Document::class);

        return Inertia::render('documents/Index', [
            'documents' => Document::query()
                ->with(['matter', 'uploader'])
                ->latest()
                ->paginate(15),
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function create(Matter $matter): Response
    {
        $this->authorize('create', Document::class);

        return Inertia::render('documents/Create', [
            'matter' => $matter,
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function store(StoreDocumentRequest $request, Matter $matter): RedirectResponse
    {
        $this->authorize('create', Document::class);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        /** @var User $user */
        $user = $request->user();

        $document = $this->documentUploadService->upload(
            $file,
            $matter,
            $user,
            $request->validated('title'),
        );

        return to_route('documents.show', $document);
    }

    public function show(Request $request, Document $document): Response
    {
        $this->authorize('view', $document);

        $this->logDocumentAction($document, $request, 'viewed');
        $document->load(
            'matter',
            'uploader',
            'classification',
            'extractedData',
            'annotations.user:id,name',
        );
        $recentActivity = $document->auditLogs()
            ->with('user:id,name')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $auditLog): array => DocumentReviewWorkspacePresenter::auditLog($auditLog))
            ->values();

        return Inertia::render('documents/Show', [
            'document' => $document,
            'recentActivity' => $recentActivity,
            'reviewWorkspace' => $this->reviewWorkspacePayload($document, $request),
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function edit(Document $document): Response
    {
        $this->authorize('update', $document);

        return Inertia::render('documents/Edit', [
            'document' => $document->load('matter'),
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $document->update($request->validated());

        return to_route('documents.show', $document);
    }

    public function review(Request $request, Document $document): RedirectResponse
    {
        return $this->transitionForManualReview(
            request: $request,
            document: $document,
            toStatus: 'reviewed',
        );
    }

    public function approve(Request $request, Document $document): RedirectResponse
    {
        return $this->transitionForManualReview(
            request: $request,
            document: $document,
            toStatus: 'approved',
        );
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        /** @var User $user */
        $user = $request->user();

        $this->documentUploadService->delete($document, $user);
        $document->delete();

        return to_route('documents.index');
    }

    public function download(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('view', $document);

        $this->logDocumentAction($document, $request, 'downloaded');

        return redirect()->away($this->documentUploadService->generatePresignedUrl($document));
    }

    public function preview(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        if (! $this->documentUploadService->supportsInlinePreview($document)) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        try {
            $stream = $this->documentUploadService->readStream($document);
        } catch (FileNotFoundException) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        return response()->stream(
            function () use ($stream): void {
                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            HttpResponse::HTTP_OK,
            [
                'Content-Type' => $this->documentUploadService->previewMimeType($document),
                'Content-Disposition' => sprintf('inline; filename="%s"', addslashes($document->file_name)),
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    protected function logDocumentAction(Document $document, Request $request, string $action): void
    {
        $document->auditLogs()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'metadata' => [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);
    }

    protected function transitionForManualReview(Request $request, Document $document, string $toStatus): RedirectResponse
    {
        $this->authorize('approve', $document);

        $fromStatus = (string) $document->status;

        if (! $this->documentStatusTransitionService->canTransition($fromStatus, $toStatus)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Document cannot transition from [%s] to [%s].',
                    $fromStatus,
                    $toStatus,
                ),
            ]);
        }

        $this->documentStatusTransitionService->transition(
            document: $document,
            toStatus: $toStatus,
            consumerName: 'manual-review',
            messageId: (string) Str::uuid(),
            metadata: [
                'source' => 'documents.show',
                'actor_user_id' => $request->user()?->id,
            ],
        );

        $this->logDocumentAction($document->fresh(), $request, $toStatus);

        return to_route('documents.show', $document);
    }

    /**
     * @return array{
     *     preview: array{
     *         url: string|null,
     *         available: bool,
     *         mime_type: string|null,
     *         mode: 'pdf'|'unsupported'
     *     },
     *     annotations: array<int, array{
     *         id: int,
     *         type: string,
     *         page_number: int,
     *         coordinates: array{x: float|int|string, y: float|int|string, width: float|int|string, height: float|int|string},
     *         content: string|null,
     *         created_at: string,
     *         updated_at: string,
     *         user: array{id: int, name: string}|null,
     *         is_owner: bool
     *     }>,
     *     permissions: array{can_annotate: bool}
     * }
     */
    protected function reviewWorkspacePayload(Document $document, Request $request): array
    {
        $previewAvailable = $this->documentUploadService->supportsInlinePreview($document);
        $currentUserId = $request->user()?->id;

        return [
            'preview' => [
                'url' => $previewAvailable
                    ? route('documents.preview', $document)
                    : null,
                'available' => $previewAvailable,
                'mime_type' => $document->mime_type,
                'mode' => $previewAvailable ? 'pdf' : 'unsupported',
            ],
            'annotations' => $document->annotations
                ->sortBy('id')
                ->values()
                ->map(fn (DocumentAnnotation $annotation): array => DocumentReviewWorkspacePresenter::annotation(
                    $annotation,
                    $currentUserId,
                ))
                ->all(),
            'permissions' => [
                'can_annotate' => $previewAvailable && $request->user()?->can('annotate', $document) === true,
            ],
        ];
    }
}
