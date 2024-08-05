<?php

namespace App\Http\Controllers;

class MainController extends Controller
{
    //
    public function index()
    {
        return view('welcome');
    }

    public function test()
    {
        return view('testpage');
    }
}
