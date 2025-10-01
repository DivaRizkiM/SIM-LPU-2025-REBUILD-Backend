<?php

namespace App\Http\Controllers;

use App\Models\Kpc;
use App\Models\Kprk;
use App\Models\Produksi;
use App\Models\UserLog;
use App\Models\ProduksiDetail;
use App\Models\Regional;
use App\Models\VerifikasiBiayaRutin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use NumberFormatter;

class BeritaAcaraPenarikanController extends Controller
{
    private function convertToRupiahTerbilang($nilai)
    {
        $formatter = new NumberFormatter('id_ID', NumberFormatter::SPELLOUT);
        return ucwords($formatter->format($nilai));
    }

    // public function pdf(Request $request)
    // {
    //     try {
    //         // Validasi input
    //         $validator = Validator::make($request->all(), [
    //             'tanggal' => 'required|date',
    //             'no_berita_acara' => 'required',
    //             'type_data' => 'required|in:1,2',
    //             'tahun' => 'required|numeric',
    //             'bulan' => 'required|numeric',
    //             'nama_pihak_pertama' => 'nullable|string',
    //             'nama_pihak_kedua' => 'nullable|string',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => $validator->errors(),
    //                 'error_code' => 'INPUT_VALIDATION_ERROR',
    //             ], 422);
    //         }

    //         // Mengambil data dari request
    //         $tanggal = $request->get('tanggal', '');
    //         $no_berita_acara = $request->get('no_berita_acara', '');
    //         $type_data = $request->get('type_data', '');
    //         $tahun = $request->get('tahun', '');
    //         $bulan = $request->get('bulan', '');
    //         $bulan = str_pad($bulan, 2, '0', STR_PAD_LEFT);
    //         $nama_pihak_pertama = $request->get('nama_pihak_pertama', 'GUNAWAN HUTAGALUNG');
    //         $nama_pihak_kedua = $request->get('nama_pihak_kedua', 'FAIZAL ROCHMAD DJOEMADI');

    //         $biaya = [];
    //         $pendapatan = [];

    //         if ($type_data == 1) {
    //             $biaya = VerifikasiBiayaRutin::select([
    //                 'verifikasi_biaya_rutin.*',
    //                 'regional.nama as nama_regional',
    //                 DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan) AS total_biaya_regional'),
    //             ])
    //                 ->where('verifikasi_biaya_rutin.tahun', $tahun)
    //                 ->leftJoin('verifikasi_biaya_rutin_detail', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
    //                 ->leftJoin('regional', 'regional.id', '=', 'verifikasi_biaya_rutin.id_regional')
    //                 ->where('verifikasi_biaya_rutin_detail.bulan', $bulan)
    //                 ->groupBy('verifikasi_biaya_rutin_detail.bulan', 'verifikasi_biaya_rutin.tahun', 'verifikasi_biaya_rutin.id_regional')
    //                 ->orderBy('triwulan')
    //                 ->orderBy('regional.nama')
    //                 ->get();

    //             foreach ($biaya as $key => $item) {
    //                 $biaya[$key]->total_biaya_regional_terbilang = $this->convertToRupiahTerbilang($item->total_biaya_regional);
    //                 $total_produksi = ProduksiDetail::join('produksi', 'produksi_detail.id_produksi', '=', 'produksi.id')
    //                 ->where('produksi.id_regional', $item->id_regional)
    //                 ->where('produksi.tahun_anggaran', $tahun)
    //                 ->where('produksi_detail.nama_bulan', $bulan)
    //                 ->groupBy('produksi.id_regional', 'produksi.triwulan', 'produksi.tahun_anggaran')->SUM('produksi_detail.pelaporan');
    //                 $biaya[$key]->pendapatan_regional = $total_produksi ?? 0;
    //             }

    //             $pendapatan = Produksi::select([
    //                 'produksi.*',
    //                 DB::raw('SUM(produksi_detail.pelaporan) AS total_regional'),
    //             ])
    //                 ->leftJoin('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
    //                 ->groupBy('produksi.triwulan', 'produksi.id_regional','produksi.tahun_anggaran')
    //                 ->get();
    //             foreach ($pendapatan as $key => $item) {
    //                 $pendapatan[$key]->total_pendapatan_regional_terbilang = $this->convertToRupiahTerbilang($item->total_regional);
    //             }
    //         } else {
    //             $biaya = VerifikasiBiayaRutin::select([
    //                 'verifikasi_biaya_rutin.*',
    //                 'regional.nama as nama_regional',
    //                 DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan_prognosa) AS total_biaya_regional'),
    //             ])
    //                 ->where('verifikasi_biaya_rutin.tahun', $tahun)
    //                 ->leftJoin('verifikasi_biaya_rutin_detail', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
    //                 ->leftJoin('regional', 'regional.id', '=', 'verifikasi_biaya_rutin.id_regional')
    //                 ->where('verifikasi_biaya_rutin_detail.bulan', $bulan)
    //                 ->groupBy('bulan', 'tahun', 'id_regional')
    //                 ->orderBy('triwulan')
    //                 ->orderBy('regional.nama')
    //                 ->get();
    //             foreach ($biaya as $key => $item) {
    //                 $biaya[$key]->total_biaya_regional_terbilang = $this->convertToRupiahTerbilang($item->total_biaya_regional);
    //                 $total_lbf = ProduksiDetail::join('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
    //                     ->select(DB::raw('SUM(ROUND(pelaporan_prognosa)) as total_lbf'))
    //                     ->where('produksi.id_regional', $item->id_regional)
    //                     ->where('produksi.tahun_anggaran', $tahun)
    //                     ->where('produksi_detail.nama_bulan', $bulan)
    //                     ->first()
    //                     ->total_lbf ?? 0; // Default to 0 if no result
    //                 $biaya[$key]->pendapatan_regional = $total_lbf ?? 0;
    //             }
    //             $pendapatan = Produksi::select([
    //                 'produksi.*',
    //                 DB::raw('SUM(produksi_detail.pelaporan_prognosa) AS total_regional'),
    //             ])
    //                 ->leftJoin('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
    //                 ->groupBy('produksi.triwulan', 'produksi.id_regional')
    //                 ->get();
    //             foreach ($pendapatan as $key => $item) {
    //                 $pendapatan[$key]->total_pendapatan_regional_terbilang = $this->convertToRupiahTerbilang($item->total_regional);
    //             }
    //         }

    //         // Menghitung tanggal kuasa
    //         $tanggalCarbon = Carbon::createFromFormat('Y-m-d', $tanggal);
    //         $hari = [
    //             1 => 'Senin',
    //             'Selasa',
    //             'Rabu',
    //             'Kamis',
    //             'Jumat',
    //             'Sabtu',
    //             'Minggu',
    //         ];
    //         $bulanArray = [
    //             1 => 'Januari',
    //             'Februari',
    //             'Maret',
    //             'April',
    //             'Mei',
    //             'Juni',
    //             'Juli',
    //             'Agustus',
    //             'September',
    //             'Oktober',
    //             'November',
    //             'Desember',
    //         ];
    //         $hariIni = $hari[$tanggalCarbon->dayOfWeek + 1];
    //         $tanggal_kuasa = $hariIni . ' tanggal ' . $tanggalCarbon->day . ' bulan ' . $bulanArray[$tanggalCarbon->month] . ' tahun ' . $tanggalCarbon->year;
    //         $nama_bulan = $bulanArray[intval($bulan)];

    //         // Mendapatkan identitas pengguna
    //         $user = Auth::user();
    //         $user_identity = null;

    //         if ($user->id_kpc) {
    //             $user_identity = Kpc::find($user->id_kpc);
    //         } elseif ($user->id_kprk) {
    //             $user_identity = Kprk::find($user->id_kprk);
    //         } elseif ($user->id_regional) {
    //             $user_identity = Regional::find($user->id_regional);
    //         }

    //         $userLog=[
    //             'timestamp' => now(),
    //             'aktifitas' =>'Cetak Berita Acara Penarikan',
    //             'modul' => 'BA Penarikan',
    //             'id_user' => Auth::user(),
    //         ];

    //         $userLog = UserLog::create($userLog);


    // return view('berita-acara.penarikan', [
    //     'tanggal' => $tanggal,
    //     'tanggal_kuasa' => $tanggal_kuasa,
    //     'nama_bulan_kuasa' => $hariIni,
    //     'nama_bulan' => $nama_bulan,
    //     'biaya' => $biaya,
    //     'pendapatan' => $pendapatan,
    //     'tahun_anggaran' => $tahun,
    //     'bulan' => $bulan,
    //     'nomor_verifikasi' => $no_berita_acara,
    //     'type' => $type_data,
    //     'nama_pihak_pertama' => $nama_pihak_pertama,
    //     'nama_pihak_kedua' => $nama_pihak_kedua,
    //     'user_identity' => $user_identity,
    //         ]);
    //     } catch (\Exception $e) {
    //         // Menangani kesalahan dengan mengembalikan respons error
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //             'error_code' => 'INTERNAL_SERVER_ERROR',
    //         ], 500);
    //     }
    // }
    public function pdf(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tanggal' => 'required|date',
                'no_berita_acara' => 'required',
                'type_data' => 'required|in:1,2',
                'tahun' => 'required|numeric',
                'bulan' => 'required|numeric',
                'nama_pihak_pertama' => 'nullable|string',
                'nama_pihak_kedua' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $tanggal = $request->get('tanggal');
            $no_berita_acara = $request->get('no_berita_acara');
            $type_data = $request->get('type_data');
            $tahun = $request->get('tahun');
            $bulan = str_pad($request->get('bulan'), 2, '0', STR_PAD_LEFT);
            $nama_pihak_pertama = $request->get('nama_pihak_pertama', 'GUNAWAN HUTAGALUNG');
            $nama_pihak_kedua = $request->get('nama_pihak_kedua', 'FAIZAL ROCHMAD DJOEMADI');

            // Ambil data langsung tanpa cache
            $biaya = DB::table('verifikasi_biaya_rutin')
                ->select(
                    'verifikasi_biaya_rutin.*',
                    'regional.nama as nama_regional',
                    DB::raw(($type_data == 1
                        ? 'SUM(verifikasi_biaya_rutin_detail.pelaporan)'
                        : 'SUM(verifikasi_biaya_rutin_detail.pelaporan_prognosa)'
                    ) . ' AS total_biaya_regional')
                )
                ->leftJoin('verifikasi_biaya_rutin_detail', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
                ->leftJoin('regional', 'regional.id', '=', 'verifikasi_biaya_rutin.id_regional')
                ->where('verifikasi_biaya_rutin.tahun', $tahun)
                ->where('verifikasi_biaya_rutin_detail.bulan', $bulan)
                ->groupBy('verifikasi_biaya_rutin_detail.bulan', 'verifikasi_biaya_rutin.tahun', 'verifikasi_biaya_rutin.id_regional')
                ->orderBy('triwulan')
                ->orderBy('regional.nama')
                ->get();

            $regionalIds = $biaya->pluck('id_regional')->unique();

            $pendapatanByRegional = DB::table('produksi_detail')
                ->join('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
                ->select(
                    'produksi.id_regional',
                    DB::raw('SUM(' . ($type_data == 1
                        ? 'produksi_detail.pelaporan'
                        : 'ROUND(produksi_detail.pelaporan_prognosa)') . ') as total_pendapatan')
                )
                ->whereIn('produksi.id_regional', $regionalIds)
                ->where('produksi.tahun_anggaran', $tahun)
                ->where('produksi_detail.nama_bulan', $bulan)
                ->groupBy('produksi.id_regional')
                ->pluck('total_pendapatan', 'produksi.id_regional');

            foreach ($biaya as $item) {
                $item->pendapatan_regional = $pendapatanByRegional[$item->id_regional] ?? 0;
                // $item->total_biaya_regional_terbilang = (new self)->convertToRupiahTerbilang($item->total_biaya_regional);
            }

            $pendapatan = DB::table('produksi')
                ->leftJoin('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->select(
                    'produksi.*',
                    DB::raw('SUM(' . ($type_data == 1
                        ? 'produksi_detail.pelaporan'
                        : 'produksi_detail.pelaporan_prognosa') . ') AS total_regional')
                )
                ->where('produksi.tahun_anggaran', $tahun)
                ->groupBy('produksi.triwulan', 'produksi.id_regional')
                ->get();

            // foreach ($pendapatan as $item) {
            //     $item->total_pendapatan_regional_terbilang = (new self)->convertToRupiahTerbilang($item->total_regional);
            // }

            $tanggalCarbon = Carbon::createFromFormat('Y-m-d', $tanggal);
            $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            $bulanArray = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            $hariIni = $hari[$tanggalCarbon->dayOfWeek];
            $tanggal_kuasa = $hariIni . ' tanggal ' . $tanggalCarbon->day . ' bulan ' . $bulanArray[$tanggalCarbon->month - 1] . ' tahun ' . $tanggalCarbon->year;
            $nama_bulan = $bulanArray[intval($bulan) - 1];

            $user = Auth::user();
            $user_identity = null;

            if ($user->id_kpc) {
                $user_identity = DB::table('kpc')->where('id', $user->id_kpc)->first();
            } elseif ($user->id_kprk) {
                $user_identity = DB::table('kprk')->where('id', $user->id_kprk)->first();
            } elseif ($user->id_regional) {
                $user_identity = DB::table('regional')->where('id', $user->id_regional)->first();
            }

            UserLog::create([
                'timestamp' => now(),
                'aktifitas' => 'Cetak Berita Acara Penarikan',
                'modul' => 'BA Penarikan',
                'id_user' => $user->id,
            ]);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('berita-acara.penarikan', [
                'tanggal' => $tanggal,
                'tanggal_kuasa' => $tanggal_kuasa,
                'nama_bulan_kuasa' => $hariIni,
                'nama_bulan' => $nama_bulan,
                'biaya' => $biaya,
                'pendapatan' => $pendapatan,
                'tahun_anggaran' => $tahun,
                'bulan' => $bulan,
                'nomor_verifikasi' => $no_berita_acara,
                'type' => $type_data,
                'nama_pihak_pertama' => $nama_pihak_pertama,
                'nama_pihak_kedua' => $nama_pihak_kedua,
                'user_identity' => $user_identity,
            ]);

            return $pdf->download('berita-acara-penarikan.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'error_code' => 'INTERNAL_SERVER_ERROR',
            ], 500);
        }
    }
}
