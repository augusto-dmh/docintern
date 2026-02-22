<?php

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use App\Services\DocumentUploadService;
use App\Support\DocumentExperienceGuardrails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(
        public DocumentUploadService $documentUploadService,
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
        $document->load('matter', 'uploader');
        $recentActivity = $document->auditLogs()
            ->with('user:id,name')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $auditLog): array => $this->formatAuditLog($auditLog))
            ->values();

        return Inertia::render('documents/Show', [
            'document' => $document,
            'recentActivity' => $recentActivity,
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

    /**
     * @return array{id: int, action: string, created_at: string, user: array{id: int, name: string}|null, ip_address: string|null}
     */
    protected function formatAuditLog(AuditLog $auditLog): array
    {
        $metadata = is_array($auditLog->metadata) ? $auditLog->metadata : [];

        return [
            'id' => $auditLog->id,
            'action' => $auditLog->action,
            'created_at' => $auditLog->created_at?->toISOString() ?? now()->toISOString(),
            'user' => $auditLog->user
                ? [
                    'id' => $auditLog->user->id,
                    'name' => $auditLog->user->name,
                ]
                : null,
            'ip_address' => is_string($metadata['ip_address'] ?? null)
                ? $metadata['ip_address']
                : null,
        ];
    }
}
