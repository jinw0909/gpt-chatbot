<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;


class TestController extends Controller
{
    public function connectAWS() {
        $recentRow = DB::connection('mysql')->table('trsi.retri_chart_data')
            ->orderBy('idx', 'desc')
            ->where('simbol', 'btcusdt')
            ->orderBy('idx')
            ->limit(10)
            ->get();

        //Return the result
        return response()->json($recentRow);
    }

    public function connectRetri() {
        $recentRow = DB::connection('mysql2')->table('beuliping')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();
        return response()->json($recentRow);
    }
}
