<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VerifikasiBiayaRutinDetail;
use App\Models\ProduksiDetail;
use Illuminate\Support\Facades\DB;
use App\Models\Produksi; // Pastikan untuk mengimpor model yang sesuai



class DashboardController extends Controller
{
    public function RealisasiBiaya()
    {
        // Mendapatkan tahun sekarang
        $tahunSekarang = date('Y');

        // Menghitung total pelaporan berdasarkan kategori biaya
        $totalPelaporan = VerifikasiBiayaRutinDetail::select('kategori_biaya', DB::raw('SUM(verifikasi_biaya_rutin_detail.pelaporan) as total_pelaporan'))
            ->leftjoin('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin')
            ->where('verifikasi_biaya_rutin.tahun', $tahunSekarang)
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
            'BIAYA ADMINISTRASI' => '#1d4ed8',
            'BIAYA OPERASI' => '#6d28d9',
            'BIAYA PEGAWAI' => '#15803d',
            'BIAYA PEMELIHARAAN' => '#a16207',
            'BIAYA PENYUSUTAN' => '#b91c1c',
            // Tambahkan kategori lain sesuai kebutuhan
        ];

        return $colors[$kategori] ?? '#374151'; // Warna default jika kategori tidak ditemukan
    }

    public function RealisasiPendapatan()
    {
        // Mendapatkan tahun sekarang
        $tahunSekarang = date('Y');

        // Menghitung total pelaporan berdasarkan kategori biaya
        $totalPelaporan = ProduksiDetail::select('kategori_produksi', DB::raw('SUM(produksi_detail.pelaporan) as total_pelaporan'))
            ->leftjoin('produksi', 'produksi.id', '=', 'produksi_detail.id_produksi')
            ->where('produksi.tahun_anggaran', $tahunSekarang)
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
            'LAYANAN BERBASIS FEE' => '#1d4ed8',
            'LAYANAN POS KOMERSIAL' => '#6d28d9',
            'LAYANAN POS UNIVERSAL' => '#15803d',

            // Tambahkan kategori lain sesuai kebutuhan
        ];

        return $colors[$kategori] ?? '#374151'; // Warna default jika kategori tidak ditemukan
    }

    public static function getRealisasiDanaLpu()
    {
        $tahun = date('Y');

        // Mengambil data realisasi
        $realisasi = DB::table('produksi')
            ->join('produksi_detail', 'produksi.id', '=', 'produksi_detail.id_produksi')
            ->select([
                'produksi.triwulan',
                'produksi.tahun_anggaran',
                DB::raw('IFNULL(SUM(produksi_detail.verifikasi), 0) as verifikasi_outgoing'),
                DB::raw('IFNULL(SUM(IF(produksi_detail.jenis_produksi = "PENGELUARAN/INCOMING", produksi_detail.verifikasi, 0)), 0) as verifikasi_incoming'),
                DB::raw('IFNULL(SUM(IF(produksi_detail.jenis_produksi = "SISA LAYANAN", produksi_detail.verifikasi, 0)), 0) as verifikasi_sisa_layanan'),
                DB::raw('IFNULL((SELECT SUM(verifikasi) FROM verifikasi_biaya_atribusi_detail GROUP BY id_verifikasi_biaya_atribusi), 0) as atribusi'),
                DB::raw('IFNULL((SELECT SUM(verifikasi) FROM verifikasi_biaya_rutin_detail
                                JOIN verifikasi_biaya_rutin ON verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin = verifikasi_biaya_rutin.id
                                WHERE verifikasi_biaya_rutin.triwulan = produksi.triwulan
                                AND verifikasi_biaya_rutin.tahun = produksi.tahun_anggaran
                                GROUP BY verifikasi_biaya_rutin.triwulan), 0) as rutin')
            ])
            ->where('produksi.tahun_anggaran', $tahun)
            ->groupBy('produksi.triwulan', 'produksi.tahun_anggaran')
            ->get();

        // Inisialisasi array untuk menyimpan selisih
        $selisih = [];

        // Menghitung selisih rutin dan verifikasi_outgoing untuk setiap triwulan
        foreach ($realisasi as $value) {
            $biaya = $value->rutin ?? 0; // Nilai rutin, default 0 jika tidak ada
            $jumlah = $value->verifikasi_outgoing ?? 0; // Nilai verifikasi_outgoing, default 0 jika tidak ada
            $selisih[$value->triwulan] = $biaya - $jumlah; // Menyimpan hasil selisih per triwulan
        }

        // Menambahkan triwulan yang tidak ada datanya dengan selisih 0
        foreach ([1, 2, 3, 4] as $triwulan) {
            if (!isset($selisih[$triwulan])) {
                $selisih[$triwulan] = 0;
            }
        }
        // dd($selisih);

        return $selisih; // Mengembalikan array selisih
    }

    public static function getTargetAnggaran(){
        $tahun = date('Y');
        $target = DB::table('target_anggaran')->where('tahun',$tahun)->first();
        return $target;
    }
    public function RealisasiBiayaChart()
    {
        $realisasiDanaLpu = $this->getRealisasiDanaLpu();

        // Menyiapkan data untuk chart
        $data = [];
        $colors = ['#0e7490', '#1d4ed8', '#15803d', '#c2410c']; // Warna untuk setiap triwulan

        // Daftar triwulan dengan urutan yang benar
        $triwulanLabels = ['Triwulan I', 'Triwulan II', 'Triwulan III', 'Triwulan IV'];

        // Mengisi data berdasarkan $realisasiDanaLpu
        foreach (range(1, 4) as $triwulanIndex) {
            $value = $realisasiDanaLpu[$triwulanIndex] ?? 0; // Menggunakan nilai dari $realisasiDanaLpu atau 0 jika tidak ada
            $data[] = [
                'category' => $triwulanLabels[$triwulanIndex - 1], // Menyesuaikan label triwulan
                'value' => $value, // Menggunakan nilai dari $realisasiDanaLpu
                'fill' => $colors[$triwulanIndex - 1] ?? '#374151', // Menggunakan warna sesuai index, default ke '#374151' jika tidak ada
            ];
        }

        // Respons akhir
        return response()->json([
            'chartType' => 'bar',
            'title' => 'Realisasi Subsidi Operasional LPU Tahun 2024',
            'data' => $data,
            'yAxisLabel' => 'Subsidi (G)',
            'xAxisLabel' => 'Triwulan',
        ]);
    }

    public function RealisasiAnggaran()
    {
        // Mendapatkan data realisasi dan target anggaran
        $realisasiDanaLpu = $this->getRealisasiDanaLpu();
        $datatargetanggaran = $this->getTargetAnggaran();

        // Menghitung realisasi untuk setiap triwulan
        $realisasi1 = $realisasiDanaLpu[1] ?? 0;
        $realisasi2 = $realisasiDanaLpu[2] ?? 0;
        $realisasi3 = $realisasiDanaLpu[3] ?? 0;
        $realisasi4 = $realisasiDanaLpu[4] ?? 0;

        // Menghitung total realisasi
        $totalRealisasi = $realisasi1 + $realisasi2 + $realisasi3 + $realisasi4;
        $nominal = $datatargetanggaran->nominal ?? 0;

        // Menghitung persentase realisasi
        $sudahterealisasi = $nominal != 0 ? ($totalRealisasi / $nominal) * 100 : 0;

        // Respons akhir
        return response()->json([
            'chartType' => 'gauge',
            'title' => 'Realisasi Anggaran',
            'value' => $sudahterealisasi, // Persentase realisasi
            'min' => 0,
            'max' => 100,
            'thresholds' => [
                ['fill' => '#b91c1c', 'from' => 0, 'to' => 33],
                ['fill' => '#a16207', 'from' => 34, 'to' => 66],
                ['fill' => '#15803d', 'from' => 67, 'to' => 100],
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
