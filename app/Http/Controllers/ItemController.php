<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $request->user()->items()->withTrashed()->get(),
        ]);
    }

    public function store(ItemRequest $request): JsonResponse
    {
        $item = $request->user()->items()->create([
            'id' => $request->string('id')->toString(),
            'ciphertext' => $request->string('ciphertext')->toString(),
            'iv' => $request->string('iv')->toString(),
            'version' => 1,
        ]);

        return response()->json($item, 201);
    }

    public function update(ItemRequest $request, string $id): JsonResponse
    {
        $item = $request->user()->items()->withTrashed()->findOrFail($id);

        // Optimistic concurrency: if another device wrote first, overwrite nothing.
        $affected = $request->user()->items()
            ->whereKey($id)
            ->where('version', $request->integer('version'))
            ->update([
                'ciphertext' => $request->string('ciphertext')->toString(),
                'iv' => $request->string('iv')->toString(),
                'version' => $request->integer('version') + 1,
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            return response()->json([
                'message' => 'The item changed on another device.',
                'item' => $item->fresh(),
            ], 409);
        }

        return response()->json($item->fresh());
    }

    public function destroy(Request $request, string $id): Response
    {
        $request->user()->items()->findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $item = $request->user()->items()->onlyTrashed()->findOrFail($id);
        $item->restore();

        return response()->json($item->fresh());
    }

    public function export(Request $request): JsonResponse
    {
        return response()->json([
            'format' => 'vault-export-v1',
            'exported_at' => now()->toIso8601String(),
            'items' => $request->user()->items()->withTrashed()->get(),
        ]);
    }
}
