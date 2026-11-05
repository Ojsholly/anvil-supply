<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// The human half of the OAuth dance — Passport redirects unauthenticated
// /oauth/authorize hits here; after sign-in they land back on consent.
Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

// Demo MCP-client landing — where the authorization server's redirect comes
// back carrying ?code=…&state=…. A real MCP client exchanges the code here
// and never shows it; we render it so the round trip is visible.
Route::get('/mcp-client/callback', function (Request $request) {
    return response()->json([
        'received_by' => 'mcp-client (demo stand-in)',
        'authorization_code' => $request->query('code'),
        'state' => $request->query('state'),
    ]);
});
