<?php

namespace App\Http\Controllers;

use App\Http\Requests\Matters\StoreMatterRequest;
use App\Http\Requests\Matters\UpdateMatterRequest;
use App\Models\Client;
use App\Models\Matter;
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
        return Inertia::render('matters/Create', [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMatterRequest $request): RedirectResponse
    {
        Matter::create($request->validated());

        return to_route('matters.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Matter $matter): Response
    {
        return Inertia::render('matters/Show', [
            'matter' => $matter->load('client', 'documents'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Matter $matter): Response
    {
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
        $matter->update($request->validated());

        return to_route('matters.show', $matter);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Matter $matter): RedirectResponse
    {
        $matter->delete();

        return to_route('matters.index');
    }
}
