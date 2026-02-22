<?php

namespace App\Http\Controllers;

use App\Http\Requests\Matters\StoreMatterRequest;
use App\Http\Requests\Matters\UpdateMatterRequest;
use App\Models\Client;
use App\Models\Matter;
use App\Support\DocumentExperienceGuardrails;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MatterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Matter::class);

        return Inertia::render('matters/Index', [
            'matters' => Matter::query()
                ->with('client')
                ->latest()
                ->paginate(15),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', Matter::class);

        return Inertia::render('matters/Create', [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMatterRequest $request): RedirectResponse
    {
        $this->authorize('create', Matter::class);

        Matter::create($request->validated());

        return to_route('matters.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Matter $matter): Response
    {
        $this->authorize('view', $matter);

        return Inertia::render('matters/Show', [
            'matter' => $matter->load([
                'client',
                'documents' => fn ($query) => $query
                    ->with('uploader')
                    ->latest(),
            ]),
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Matter $matter): Response
    {
        $this->authorize('update', $matter);

        return Inertia::render('matters/Edit', [
            'matter' => $matter,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMatterRequest $request, Matter $matter): RedirectResponse
    {
        $this->authorize('update', $matter);

        $matter->update($request->validated());

        return to_route('matters.show', $matter);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Matter $matter): RedirectResponse
    {
        $this->authorize('delete', $matter);

        $matter->delete();

        return to_route('matters.index');
    }
}
