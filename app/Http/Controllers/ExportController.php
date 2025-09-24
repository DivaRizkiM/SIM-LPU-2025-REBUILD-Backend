<?php

namespace App\Http\Controllers;

use App\Exports\BiayaExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function exportBiaya(Request $request)
    {
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');
        $filename = 'biaya_' . $tahun . '_' . $bulan . '.xlsx';
        return Excel::download(new BiayaExport($tahun, $bulan), $filename);
    }
}