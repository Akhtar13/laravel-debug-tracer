<?php

namespace Akhtar\LaravelDebugTracer\Http\Controllers;

use Illuminate\Routing\Controller;


class DashboardController extends Controller
{
    public function index()
    {
        return view('debug-tracer::dashboard');
    }

    public function logs($sessionId)
    {
        $path = storage_path("debug-traces/{$sessionId}.log");

        if (!file_exists($path)) {
            return response()->json([]);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        return response()->json(array_map(fn($l) => json_decode($l, true), $lines));
    }
}