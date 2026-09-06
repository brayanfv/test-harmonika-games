<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contacts = $request->user()
            ->contacts()
            ->latest()
            ->get();

        return response()->json($contacts);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $request->user()->contacts()->create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        return response()->json($contact, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $contact = $request->user()
            ->contacts()
            ->findOrFail($id);

        return response()->json($contact);
    }

    public function update(UpdateContactRequest $request, int $id): JsonResponse
    {
        $contact = $request->user()
            ->contacts()
            ->findOrFail($id);

        $contact->update($request->validated());

        return response()->json($contact);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $contact = $request->user()
            ->contacts()
            ->findOrFail($id);

        $contact->delete();

        return response()->json(null, 204);
    }
    
}

