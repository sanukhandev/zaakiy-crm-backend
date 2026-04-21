<?php

namespace App\Http\Controllers;

class DocsController extends Controller
{
    public function swagger()
    {
        return view('swagger');
    }
}
