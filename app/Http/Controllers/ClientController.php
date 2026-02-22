<?php

namespace App\Http\Controllers;

use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use App\Support\DocumentExperienceGuardrails;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Client::class);

        return Inertia::render('clients/Index', [
            'clients' => Client::query()
                ->latest()
                ->paginate(15),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', Client::class);

        return Inertia::render('clients/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClientRequest $request): RedirectResponse
    {
        $this->authorize('create', Client::class);

        Client::create($request->validated());

        return to_route('clients.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client): Response
    {
        $this->authorize('view', $client);

        return Inertia::render('clients/Show', [
            'client' => $client->load([
                'matters' => fn ($query) => $query
                    ->withCount('documents')
                    ->latest(),
            ]),
            'documentExperience' => DocumentExperienceGuardrails::inertiaPayload(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client): Response
    {
        $this->authorize('update', $client);

        return Inertia::render('clients/Edit', [
            'client' => $client,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $client->update($request->validated());

        return to_route('clients.show', $client);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return to_route('clients.index');
    }
}
