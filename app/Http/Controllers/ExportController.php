<?php

namespace App\Http\Controllers;

use App\Exports\BiayaExport;
use Illuminate\Http\Request;
use App\Exports\PendapatanExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BiayaMultiSheetExport;
use App\Exports\BiayaRekapExport;

class ExportController extends Controller
{
    public function exportBiaya(Request $request)
    {
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');
        $filename = 'biaya_' . $tahun . '_' . $bulan . '.xlsx';
        return Excel::download(new BiayaExport($tahun, $bulan), $filename);
    }

    public function exportPendapatan(Request $request)
    {
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');
        $filename = 'pendapatan_' . $tahun . '_' . $bulan . '.xlsx';
        return Excel::download(new PendapatanExport($tahun, $bulan), $filename);
    }

    public function exportRekapBiaya(Request $request)
    {
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');
        $filename = 'rekap_biaya_' . $tahun . '_' . $bulan . '.xlsx';
        return Excel::download(new BiayaRekapExport($tahun, $bulan), $filename);
    }
}