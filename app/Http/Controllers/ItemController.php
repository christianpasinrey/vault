<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ItemController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        return response()->json([
            'items' => $peticion->user()->items()->withTrashed()->get(),
        ]);
    }

    public function store(ItemRequest $peticion): JsonResponse
    {
        $item = $peticion->user()->items()->create([
            'id' => $peticion->string('id')->toString(),
            'ciphertext' => $peticion->string('ciphertext')->toString(),
            'iv' => $peticion->string('iv')->toString(),
            'version' => 1,
        ]);

        return response()->json($item, 201);
    }

    public function update(ItemRequest $peticion, string $id): JsonResponse
    {
        $item = $peticion->user()->items()->withTrashed()->findOrFail($id);

        // Concurrencia optimista: si otro dispositivo escribió antes, no pisamos nada.
        $afectadas = $peticion->user()->items()
            ->whereKey($id)
            ->where('version', $peticion->integer('version'))
            ->update([
                'ciphertext' => $peticion->string('ciphertext')->toString(),
                'iv' => $peticion->string('iv')->toString(),
                'version' => $peticion->integer('version') + 1,
                'updated_at' => now(),
            ]);

        if ($afectadas === 0) {
            return response()->json([
                'message' => 'El ítem cambió desde otro dispositivo.',
                'item' => $item->fresh(),
            ], 409);
        }

        return response()->json($item->fresh());
    }

    public function destroy(Request $peticion, string $id): Response
    {
        $peticion->user()->items()->findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(Request $peticion, string $id): JsonResponse
    {
        $item = $peticion->user()->items()->onlyTrashed()->findOrFail($id);
        $item->restore();

        return response()->json($item->fresh());
    }

    public function export(Request $peticion): JsonResponse
    {
        return response()->json([
            'formato' => 'vault-export-v1',
            'exportado_en' => now()->toIso8601String(),
            'items' => $peticion->user()->items()->withTrashed()->get(),
        ]);
    }
}
