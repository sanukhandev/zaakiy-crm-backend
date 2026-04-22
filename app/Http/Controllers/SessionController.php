<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function getSession(Request $request)
    {
        $auth = $request->attributes->get('auth');

        return response()->json([
            'success' => true,
            'data' => $auth,
        ]);
    }
}
