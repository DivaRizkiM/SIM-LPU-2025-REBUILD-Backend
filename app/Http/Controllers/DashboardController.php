<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VerifikasiBiayaRutinDetail;
use App\Models\ProduksiDetail;
use Illuminate\Support\Facades\DB;
use App\Models\Produksi; // Pastikan untuk mengimpor model yang sesuai



class DashboardController extends Controller
{
    public function RealisasiBiaya(Request $request)
    {
        // Mendapatkan tahun sekarang
        $tahunSekarang = date('Y');

        $id_regional = $request->get('id_regional');
        $id_kprk     = $request->get('id_kprk');
        $id_kpc      = $request->get('id_kpc');

        // Menghitung total pelaporan berdasarkan kategori biaya
        $totalPelaporan = VerifikasiBiayaRutinDetail::select('kategori_biaya', DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan) as total_pelaporan'))
            ->leftjoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
            ->where('verifikasi_biaya_rutin.tahun', $tahunSekarang)
            ->when($id_regional, function ($query, $id_regional) {
                return $query->where('verifikasi_biaya_rutin.id_regional', $id_regional);
            })
            ->when($id_kprk, function ($query, $id_kprk) {
                return $query->where('verifikasi_biaya_rutin.id_kprk', $id_kprk);
            })
            ->when($id_kpc, function ($query, $id_kpc) {
                return $query->where('verifikasi_biaya_rutin.id_kpc', $id_kpc);
            })
            ->groupBy('verifikasi_biaya_rutin_detail.kategori_biaya')
            ->get();

        // dd($totalPelaporan);

        // Menghitung total keseluruhan
        $totalKeseluruhan = $totalPelaporan->sum('total_pelaporan');

        // Daftar semua kategori biaya yang ingin ditampilkan
        $semuaKategori = [
            'BIAYA ADMINISTRASI',
            'BIAYA OPERASI',
            'BIAYA PEGAWAI',
            'BIAYA PEMELIHARAAN',
            'BIAYA PENYUSUTAN',
            // Tambahkan kategori lain sesuai kebutuhan
        ];

        // Format data sesuai dengan respons yang diinginkan
        $formattedData = collect($semuaKategori)->map(function ($kategori) use ($totalPelaporan, $totalKeseluruhan) {
            // Mencari total pelaporan untuk kategori ini
            $pelaporan = $totalPelaporan->firstWhere('kategori_biaya', $kategori);
            $totalPelaporanNilai = $pelaporan ? $pelaporan->total_pelaporan : 0;

            $persentase = $totalKeseluruhan > 0 ? ($totalPelaporanNilai / $totalKeseluruhan) * 100 : 0;

            return [
                'category' => $kategori,
                'total' => $totalPelaporanNilai,
                'value' => round($persentase, 2), // Bulatkan persentase hingga 2 desimal
                'fill' => $this->getColorByCategoryBiaya($kategori), // Mendapatkan warna berdasarkan kategori
            ];
        });

        // Respons akhir
        return response()->json([
            'chartType' => 'pie',
            'title' => 'Realisasi Biaya',
            'total' => $totalKeseluruhan, // Total keseluruhan
            'data' => $formattedData,
        ]);
    }

    // Fungsi untuk mendapatkan warna berdasarkan kategori
    private function getColorByCategoryBiaya($kategori)
    {
        $colors = [
            'BIAYA ADMINISTRASI' => '#636CCB',
            'BIAYA OPERASI' => '#6E8CFB',
            'BIAYA PEGAWAI' => '#3C467B',
            'BIAYA PEMELIHARAAN' => '#50589C',
            'BIAYA PENYUSUTAN' => '#023047',
            // Tambahkan kategori lain sesuai kebutuhan
        ];

        return $colors[$kategori] ?? '#374151'; // Warna default jika kategori tidak ditemukan
    }

    public function RealisasiPendapatan(Request $request)
    {
        // Mendapatkan tahun sekarang
        $tahunSekarang = date('Y');

        $id_regional = $request->get('id_regional');
        $id_kprk     = $request->get('id_kprk');
        $id_kpc      = $request->get('id_kpc');

        // Menghitung total pelaporan berdasarkan kategori biaya
        $totalPelaporan = ProduksiDetail::select('kategori_produksi', DB::raw('SUM(produksi_detail.pelaporan) as total_pelaporan'))
            ->leftjoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
            ->where('produksi.tahun_anggaran', $tahunSekarang)
            ->when($id_regional, function ($query, $id_regional) {
                return $query->where('produksi.id_regional', $id_regional);
            })
            ->when($id_kprk, function ($query, $id_kprk) {
                return $query->where('produksi.id_kprk', $id_kprk);
            })
            ->when($id_kpc, function ($query, $id_kpc) {
                return $query->where('produksi.id_kpc', $id_kpc);
            })
            ->groupBy('produksi_detail.kategori_produksi')
            ->get();

        // dd($totalPelaporan);

        // Menghitung total keseluruhan
        $totalKeseluruhan = $totalPelaporan->sum('total_pelaporan');

        // Daftar semua kategori biaya yang ingin ditampilkan
        $semuaKategori = [
            'LAYANAN BERBASIS FEE',
            'LAYANAN POS KOMERSIL',
            'LAYANAN POS UNIVERSAL',
            // Tambahkan kategori lain sesuai kebutuhan
        ];

        // Format data sesuai dengan respons yang diinginkan
        $formattedData = collect($semuaKategori)->map(function ($kategori) use ($totalPelaporan, $totalKeseluruhan) {
            // Mencari total pelaporan untuk kategori ini
            $pelaporan = $totalPelaporan->firstWhere('kategori_produksi', $kategori);
            $totalPelaporanNilai = $pelaporan ? $pelaporan->total_pelaporan : 0;

            $persentase = $totalKeseluruhan > 0 ? ($totalPelaporanNilai / $totalKeseluruhan) * 100 : 0;

            return [
                'category' => $kategori,
                'total' => $totalPelaporanNilai,
                'value' => round($persentase, 2), // Bulatkan persentase hingga 2 desimal
                'fill' => $this->getColorByCategoryPendapatan($kategori), // Mendapatkan warna berdasarkan kategori
            ];
        });

        // Respons akhir
        return response()->json([
            'chartType' => 'donut',
            'title' => 'Realisasi Pendapatan',
            'total' => $totalKeseluruhan, // Total keseluruhan
            'data' => $formattedData,
        ]);
    }

    // Fungsi untuk mendapatkan warna berdasarkan kategori
    private function getColorByCategoryPendapatan($kategori)
    {
        $colors = [
            'LAYANAN BERBASIS FEE' => '#3C467B',
            'LAYANAN POS KOMERSIL' => '#6E8CFB',
            'LAYANAN POS UNIVERSAL' => '#50589C',

            // Tambahkan kategori lain sesuai kebutuhan
        ];

        return $colors[$kategori] ?? '#636CCB'; // Warna default jika kategori tidak ditemukan
    }

    public static function getRealisasiDanaLpu(array $filterParams = [])
    {
        $tahun = date('Y');

        $detailColMap = [
            'id_regional' => 'produksi.id_regional',
            'id_kprk'     => 'produksi.id_kprk',
            'id_kpc'      => 'produksi.id_kpc',
        ];

        $rutinColMap = [
            'id_regional' => 'verifikasi_biaya_rutin.id_regional',
            'id_kprk'     => 'verifikasi_biaya_rutin.id_kprk',
            'id_kpc'      => 'verifikasi_biaya_rutin.id_kpc',
        ];

        $applyFilters = function (\Illuminate\Database\Query\Builder $q, array $map) use ($filterParams) {
            foreach ($filterParams as $key => $val) {
                if ($val === '' || $val === null) continue;
                if (isset($map[$key])) $q->where($map[$key], $val);
            }
            return $q;
        };

        $detailAgg = DB::table('produksi')
            ->join('produksi_detail', 'produksi.id', '=', 'produksi_detail.id_produksi')
            ->when(true, fn($q) => $applyFilters($q, $detailColMap))
            ->where('produksi.tahun_anggaran', $tahun)
            ->groupBy(DB::raw('CAST(produksi.bulan AS UNSIGNED)'), 'produksi.tahun_anggaran')
            ->select([
                DB::raw('CAST(produksi.bulan AS UNSIGNED) AS bulan_num'),
                'produksi.tahun_anggaran',
                DB::raw('COALESCE(SUM(CASE WHEN produksi_detail.jenis_produksi = "PENGELUARAN/OUTGOING" THEN produksi_detail.verifikasi ELSE 0 END), 0) AS verifikasi_outgoing'),
            ]);

        $rutinAgg = DB::table('verifikasi_biaya_rutin')
            ->join('verifikasi_biaya_rutin_detail', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin', '=', 'verifikasi_biaya_rutin.id')
            ->when(true, fn($q) => $applyFilters($q, $rutinColMap))
            ->where('verifikasi_biaya_rutin.tahun', $tahun)
            ->groupBy(DB::raw('CAST(verifikasi_biaya_rutin_detail.bulan AS UNSIGNED)'), 'verifikasi_biaya_rutin.tahun')
            ->select([
                DB::raw('CAST(verifikasi_biaya_rutin_detail.bulan AS UNSIGNED) AS bulan_num'),
                DB::raw('verifikasi_biaya_rutin.tahun AS tahun_anggaran'),
                DB::raw('COALESCE(SUM(verifikasi_biaya_rutin_detail.verifikasi), 0) AS rutin'),
            ]);

        $joined = DB::query()
            ->fromSub($detailAgg, 'D')
            ->leftJoinSub($rutinAgg, 'R', function ($join) {
                $join->on('R.bulan_num', '=', 'D.bulan_num')
                    ->on('R.tahun_anggaran', '=', 'D.tahun_anggaran');
            })
            ->select([
                'D.bulan_num',
                'D.tahun_anggaran',
                DB::raw('COALESCE(D.verifikasi_outgoing, 0) AS verifikasi_outgoing'),
                DB::raw('COALESCE(R.rutin, 0) AS rutin'),
            ])
            ->orderBy('D.bulan_num')
            ->get();

        // Isi array 1..12 semua 0.0 dulu, lalu timpa yang ada datanya
        $selisih = array_fill(1, 12, 0.0);
        foreach ($joined as $row) {
            $b = (int) $row->bulan_num;
            if ($b >= 1 && $b <= 12) {
                $selisih[$b] = (float) $row->rutin - (float) $row->verifikasi_outgoing;
            }
        }

        return $selisih;
    }



    public static function getTargetAnggaran()
    {
        $tahun = date('Y');
        $target = DB::table('target_anggaran')->where('tahun', $tahun)->first();
        return $target;
    }
    public function RealisasiBiayaChart(Request $request)
    {
        $tahun = date('Y');
        $realisasiDanaLpu = $this->getRealisasiDanaLpu($request->all());

        $bulanLabel = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

        // opsional: bikin 12 warna sederhana (atau hapus 'fill' biar pakai default)
        $colors = [
            '#0e7490','#1d4ed8','#15803d','#c2410c',
            '#7c3aed','#0369a1','#ca8a04','#b91c1c',
            '#2563eb','#16a34a','#ea580c','#475569',
        ];

        $data = [];
        foreach (range(1, 12) as $i) {
            $data[] = [
                'category' => $bulanLabel[$i - 1],
                'value'    => (float) ($realisasiDanaLpu[$i] ?? 0),
                'fill'     => $colors[$i - 1],
            ];
        }

        return response()->json([
            'chartType'  => 'bar',
            'title'      => "Realisasi Subsidi Operasional LPU Tahun {$tahun}",
            'data'       => $data,
            'yAxisLabel' => 'Rupiah',
            'xAxisLabel' => 'Bulan',
        ]);
    }


    public function RealisasiAnggaran(Request $request)
    {
        $filterParams = [
            'id_regional' => $request->input('id_regional', ''),
            'id_kprk'     => $request->input('id_kprk', ''),
            'id_kpc'      => $request->input('id_kpc', ''),
        ];

        $realisasiDanaLpu = $this->getRealisasiDanaLpu($filterParams);
        $realisasiDanaLpu = array_replace(array_fill(1, 12, 0.0), $realisasiDanaLpu);

        $q1 = (float)$realisasiDanaLpu[1] + (float)$realisasiDanaLpu[2] + (float)$realisasiDanaLpu[3];
        $q2 = (float)$realisasiDanaLpu[4] + (float)$realisasiDanaLpu[5] + (float)$realisasiDanaLpu[6];
        $q3 = (float)$realisasiDanaLpu[7] + (float)$realisasiDanaLpu[8] + (float)$realisasiDanaLpu[9];
        $q4 = (float)$realisasiDanaLpu[10] + (float)$realisasiDanaLpu[11] + (float)$realisasiDanaLpu[12];

        $totalRealisasi = $q1 + $q2 + $q3 + $q4;
        
        $datatargetanggaran = $this->getTargetAnggaran();
        $nominal = (float) ($datatargetanggaran->nominal ?? 0);

        $sudahterealisasi = $nominal > 0 ? ($totalRealisasi / $nominal) * 100 : 0;
        $sudahterealisasi = round($sudahterealisasi, 2);

        return response()->json([
            'chartType' => 'gauge',
            'title'     => 'Realisasi Anggaran',
            'value'     => $sudahterealisasi, // persen
            'min'       => 0,
            'max'       => 100,
            'thresholds' => [
                ['fill' => '#b91c1c', 'from' => 0,  'to' => 33],
                ['fill' => '#a16207', 'from' => 34, 'to' => 66],
                ['fill' => '#15803d', 'from' => 67, 'to' => 100],
            ],
            // bonus info kalau mau dipakai di UI lain:
            'meta' => [
                'q1' => $q1, 'q2' => $q2, 'q3' => $q3, 'q4' => $q4,
                'total_realisasi' => $totalRealisasi,
                'target_nominal'  => $nominal,
            ],
        ]);
    }

    // Fungsi untuk mendapatkan warna berdasarkan kategori
    private function getColorByCategoryBiayaChart($kategori)
    {
        $colors = [
            'BIAYA ADMINISTRASI' => '#1d4ed8',
            'BIAYA OPERASI' => '#6d28d9',
            'BIAYA PEGAWAI' => '#15803d',
            'BIAYA PEMELIHARAAN' => '#c2410c',
            'BIAYA PENYUSUTAN' => '#b91c1c',
            // Tambahkan kategori lain sesuai kebutuhan
        ];

        return $colors[$kategori] ?? '#374151'; // Warna default jika kategori tidak ditemukan
    }
}
