<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /** Devuelve los ítems tal cual están en la base: blobs que el servidor no entiende. */
    public function index(Request $peticion): JsonResponse
    {
        return response()->json(
            $peticion->user()->items()
                ->get(['id', 'ciphertext', 'iv', 'version', 'deleted_at', 'created_at', 'updated_at'])
        );
    }
}
