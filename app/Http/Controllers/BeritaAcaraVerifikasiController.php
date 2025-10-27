<?php

namespace App\Http\Controllers;

use App\Models\Kpc;
use App\Models\Kprk;
use App\Models\Produksi;
use App\Models\UserLog;
use App\Models\Regional;
use App\Models\VerifikasiBiayaRutinDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use NumberFormatter;

class BeritaAcaraVerifikasiController extends Controller
{
    private function spellId($nilai): string
    {
        $fmt = new NumberFormatter('id_ID', NumberFormatter::SPELLOUT);
        return ucwords($fmt->format($nilai));
    }

    public static function formatTanggal(string $tanggal): string
    {
        Carbon::setLocale('id');
        $c = Carbon::createFromFormat('Y-m-d', $tanggal);

        $hari   = $c->translatedFormat('l');  // Senin, Selasa, ...
        $bulan  = $c->translatedFormat('F');  // Januari, Februari, ...
        $fmt    = new NumberFormatter('id_ID', NumberFormatter::SPELLOUT);

        $hariTerbilang   = ucwords($fmt->format($c->day));
        $tahunTerbilang  = ucwords($fmt->format($c->year));

        return "{$hari} tanggal {$hariTerbilang} bulan {$bulan} tahun {$tahunTerbilang}";
    }

    private static function bulanNama(string $tanggal): string 
    {
        Carbon::setLocale('id');
        return Carbon::createFromFormat('Y-m-d', $tanggal)->translatedFormat('F');
    }

    public function getVerifikasi(?array $where_user_identity, int $tahun_anggaran, int $triwulan, array $kategori_biaya, ?string $lampiran = null)
    {
        $q = VerifikasiBiayaRutinDetail::join(
            'verifikasi_biaya_rutin',
            'verifikasi_biaya_rutin.id',
            '=',
            'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin'
        );

        if ($where_user_identity !== null) {
            $q->where($where_user_identity[0], $where_user_identity[1]);
        }

        $q->where('verifikasi_biaya_rutin.tahun', $tahun_anggaran)
          ->where('verifikasi_biaya_rutin.triwulan', $triwulan)
          ->whereIn('verifikasi_biaya_rutin_detail.kategori_biaya', $kategori_biaya);

        if (!is_null($lampiran)) {
            $q->where('verifikasi_biaya_rutin_detail.lampiran', $lampiran);
        }

        return $q;
    }

    private static function getPendapatanBulanan(int $tahun_anggaran, int $bulan, bool $excludeOktober = false)
    {
        $q = Produksi::join('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
            ->where('produksi.tahun_anggaran', $tahun_anggaran)
            ->where('produksi_detail.nama_bulan', $bulan);

        if ($excludeOktober) {
            $q->where('produksi_detail.nama_bulan', '!=', 10);
        }

        return $q;
    }

    private static function getVerifikasiBulanan(?array $where_user_identity, int $tahun_anggaran, int $bulan, array $kategori_biaya, ?string $lampiran = null)
    {
        $q = VerifikasiBiayaRutinDetail::join(
            'verifikasi_biaya_rutin',
            'verifikasi_biaya_rutin.id',
            '=',
            'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin'
        );

        if (!is_null($where_user_identity)) {
            $q->where($where_user_identity[0], $where_user_identity[1]);
        }

        $q->where('verifikasi_biaya_rutin.tahun', $tahun_anggaran)
          ->where('verifikasi_biaya_rutin_detail.bulan', $bulan)
          ->whereIn('verifikasi_biaya_rutin_detail.kategori_biaya', $kategori_biaya);

        if (!is_null($lampiran)) {
            $q->where('verifikasi_biaya_rutin_detail.lampiran', $lampiran);
        }

        return $q;
    }

    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tanggal_kuasa'           => 'required|date_format:Y-m-d',
                'no_verifikasi'           => 'required',
                'no_verifikasi_2'         => 'required',
                'triwulan'                => 'required|numeric|in:1,2,3,4',
                'no_perjanjian_kerja'     => 'required',
                'no_perjanjian_kerja_2'   => 'required',
                'tanggal_perjanjian'      => 'required|date_format:Y-m-d',
                'tanggal_perjanjian_2'    => 'required|date_format:Y-m-d',
                'tahun_anggaran'          => 'required|numeric',
                'nama_pihak_pertama'      => 'nullable|string',
                'nama_pihak_kedua'        => 'nullable|string',
                'penalti_penyediaan_prasarana'       => 'nullable|numeric',
                'penalti_waktu_tempuh_kiriman_surat' => 'nullable|numeric',
                'faktur_pengurang'        => 'nullable|numeric',
                'pembayaran_bulan_1'      => 'nullable|numeric',
                'pembayaran_bulan_2'      => 'nullable|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $tanggal_kuasa         = $request->get('tanggal_kuasa');
            $no_verifikasi         = $request->get('no_verifikasi');
            $no_verifikasi_2       = $request->get('no_verifikasi_2');
            $triwulan              = (int) $request->get('triwulan');
            $no_perjanjian_kerja   = $request->get('no_perjanjian_kerja');
            $no_perjanjian_kerja_2 = $request->get('no_perjanjian_kerja_2');
            $tanggal_perjanjian    = $request->get('tanggal_perjanjian');
            $tanggal_perjanjian_2  = $request->get('tanggal_perjanjian_2');
            $tahun_anggaran        = (int) $request->get('tahun_anggaran');

            $nama_pihak_pertama    = $request->get('nama_pihak_pertama', 'GUNAWAN HUTAGALUNG');
            $nama_pihak_kedua      = $request->get('nama_pihak_kedua', 'FAIZAL ROCHMAD DJOEMADI');

            $penalti_penyediaan_prasarana        = (float) $request->get('penalti_penyediaan_prasarana', 0);
            $penalti_waktu_tempuh_kiriman_surat  = (float) $request->get('penalti_waktu_tempuh_kiriman_surat', 0);
            $faktur_pengurang     = (float) $request->get('faktur_pengurang', 0);
            $pembayaran_bulan_1   = (float) $request->get('pembayaran_bulan_1', 0);
            $pembayaran_bulan_2   = (float) $request->get('pembayaran_bulan_2', 0);

            // Range bulan per triwulan
            [$bulan_pertama, $bulan_terakhir] = [
                1 => [1, 3],
                2 => [4, 6],
                3 => [7, 9],
                4 => [10, 12],
            ][$triwulan];

            // Tanggal terbilang—bulan diambil langsung dari tanggal (bukan dari kalimat!)
            $tanggal_kuasa_terbilang        = $this->formatTanggal($tanggal_kuasa);
            $tanggal_perjanjian_terbilang   = $this->formatTanggal($tanggal_perjanjian);
            $tanggal_perjanjian_2_terbilang = $this->formatTanggal($tanggal_perjanjian_2);

            $bulan_kuasa_terbilang          = self::bulanNama($tanggal_kuasa);
            $bulan_perjanjian_terbilang     = self::bulanNama($tanggal_perjanjian);
            $bulan_perjanjian_2_terbilang   = self::bulanNama($tanggal_perjanjian_2);

            // Format “DD NamaBulan YYYY”
            Carbon::setLocale('id');
            $tanggal = Carbon::createFromFormat('Y-m-d', $tanggal_perjanjian)->translatedFormat('d F Y');

            // Identitas user → filter
            $user = Auth::user();
            $user_identity = null;
            $where_user_identity = null;

            if ($user->id_kpc) {
                $user_identity = Kpc::find($user->id_kpc);
                // asumsi id_kpc di verifikasi_biaya_rutin = nomor_dirian
                $where_user_identity = ['verifikasi_biaya_rutin.id_kpc', $user_identity->nomor_dirian];
            } elseif ($user->id_kprk) {
                $user_identity = Kprk::find($user->id_kprk);
                $where_user_identity = ['verifikasi_biaya_rutin.id_kprk', $user_identity->id];
            } elseif ($user->id_regional) {
                $user_identity = Regional::find($user->id_regional);
                $where_user_identity = ['verifikasi_biaya_rutin.regional', $user_identity->id];
            }

            // ===== Aggregates (rutin, operasi, atributif, pendapatan) =====
            $kategoriRutin = ['BIAYA PEGAWAI', 'BIAYA PEMELIHARAAN', 'BIAYA ADMINISTRASI', 'BIAYA PENYUSUTAN', 'BIAYA OPERASI'];
            $rekAtributif  = [5102060006, 5102060008, 5102060009, 5102060010, 5102060011];

            $pelaporan_biaya_rutin = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, $kategoriRutin)
                ->sum('verifikasi_biaya_rutin_detail.pelaporan');

            $pelaporan_biaya_rutin_prognosa = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, $kategoriRutin)
                ->where('verifikasi_biaya_rutin_detail.bulan', '!=', 10)
                ->sum('verifikasi_biaya_rutin_detail.pelaporan_prognosa');

            $pelaporan_biaya_operasi = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'], 'Y')
                ->sum('verifikasi_biaya_rutin_detail.pelaporan');

            $verifikasi_biaya_rutin = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, $kategoriRutin)
                ->whereNotIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', $rekAtributif)
                ->sum('verifikasi_biaya_rutin_detail.verifikasi');

            $verifikasi_biaya_rutin_prognosa = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, $kategoriRutin)
                ->whereNotIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', $rekAtributif)
                ->where('verifikasi_biaya_rutin_detail.bulan', '!=', 10)
                ->sum('verifikasi_biaya_rutin_detail.pelaporan_prognosa');

            $verifikasi_biaya_rutin_operasi = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'], 'Y')
                ->sum('verifikasi_biaya_rutin_detail.verifikasi');

            $pelaporan_biaya_atribusi = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'])
                ->whereIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', $rekAtributif)
                ->sum('verifikasi_biaya_rutin_detail.pelaporan');

            $pelaporan_biaya_atribusi_prognosa = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'])
                ->whereIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', $rekAtributif)
                ->where('verifikasi_biaya_rutin_detail.bulan', '!=', 10)
                ->sum('verifikasi_biaya_rutin_detail.pelaporan_prognosa');

            $verifikasi_biaya_atribusi = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'])
                ->whereIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', $rekAtributif)
                ->sum('verifikasi_biaya_rutin_detail.verifikasi');

            $verifikasi_biaya_atribusi_prognosa = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'])
                ->whereIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', $rekAtributif)
                ->where('verifikasi_biaya_rutin_detail.bulan', '!=', 10)
                ->sum('verifikasi_biaya_rutin_detail.pelaporan_prognosa');

            $verifikasi_biaya_pendapatan = Produksi::join('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->where('produksi.tahun_anggaran', $tahun_anggaran)
                ->where('produksi.triwulan', $triwulan)
                ->sum('produksi_detail.verifikasi');

            $total_pelaporan_biaya_atribusi = $pelaporan_biaya_atribusi + $pelaporan_biaya_atribusi_prognosa;
            $total_verifikasi_biaya_atribusi = $verifikasi_biaya_atribusi + $verifikasi_biaya_atribusi_prognosa;
            $total_biaya_pelaporan = $pelaporan_biaya_rutin + $pelaporan_biaya_rutin_prognosa;
            $total_pelaporan = $total_biaya_pelaporan + $pelaporan_biaya_atribusi;
            $total_verifikasi = $verifikasi_biaya_rutin + $verifikasi_biaya_atribusi;
            $total_biaya_verifikasi = $verifikasi_biaya_rutin + $verifikasi_biaya_rutin_prognosa;
            $bulannoverif = (int) date('n'); // 1..12
            $total_bo_lpu = $total_pelaporan - $total_verifikasi;

            $total_faktor = $penalti_penyediaan_prasarana + $penalti_waktu_tempuh_kiriman_surat + $faktur_pengurang;
            $total = $total_bo_lpu - $total_faktor;

            $total_pelaporan_biaya_bulan_pertama = 0;
            $total_pelaporan_pendapatan_bulan_pertama = 0;
            $total_verifikasi_pendapatan_bulan_pertama = 0;

            $total_pelaporan_biaya_bulan_kedua = 0;
            $total_pelaporan_pendapatan_bulan_kedua = 0;
            $total_verifikasi_pendapatan_bulan_kedua = 0;

            $total_pelaporan_biaya_bulan_ketiga = 0;
            $total_pelaporan_pendapatan_bulan_ketiga = 0;
            $total_verifikasi_pendapatan_bulan_ketiga = 0;

            $biaya_langsung = $total_biaya_pelaporan - $total_pelaporan_biaya_atribusi;

            for ($i = $bulan_pertama; $i <= $bulan_terakhir; $i++) {
                $angka_biaya_rutin = self::getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $i, $kategoriRutin)
                    ->sum('verifikasi_biaya_rutin_detail.pelaporan');

                $angka_biaya_operasi = self::getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $i, ['BIAYA OPERASI'], 'Y')
                    ->sum('verifikasi_biaya_rutin_detail.pelaporan');

                $angka_biaya_atribusi = self::getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $i, ['BIAYA OPERASI'])
                    ->whereIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', $rekAtributif)
                    ->sum('verifikasi_biaya_rutin_detail.pelaporan');

                $angka_pendapatan = self::getPendapatanBulanan($tahun_anggaran, $i, false)
                    ->sum('produksi_detail.pelaporan');

                $angka_pendapatan_prognosa = self::getPendapatanBulanan($tahun_anggaran, $i, true)
                    ->sum('produksi_detail.pelaporan_prognosa');

                $angka_pendapatan_verifikasi = self::getPendapatanBulanan($tahun_anggaran, $i, false)
                    ->sum('produksi_detail.verifikasi');

                if ($i === $bulan_pertama) {
                    $total_pelaporan_biaya_bulan_pertama        += $angka_biaya_rutin + $angka_biaya_operasi + $angka_biaya_atribusi;
                    $total_pelaporan_pendapatan_bulan_pertama    += $angka_pendapatan + $angka_pendapatan_prognosa;
                    $total_verifikasi_pendapatan_bulan_pertama   += $angka_pendapatan_verifikasi;
                } elseif ($i === $bulan_terakhir) {
                    $total_pelaporan_biaya_bulan_ketiga          += $angka_biaya_rutin + $angka_biaya_operasi + $angka_biaya_atribusi;
                    $total_pelaporan_pendapatan_bulan_ketiga     += $angka_pendapatan + $angka_pendapatan_prognosa;
                    $total_verifikasi_pendapatan_bulan_ketiga    += $angka_pendapatan_verifikasi;
                } else {
                    $total_pelaporan_biaya_bulan_kedua           += $angka_biaya_rutin + $angka_biaya_operasi + $angka_biaya_atribusi;
                    $total_pelaporan_pendapatan_bulan_kedua      += $angka_pendapatan + $angka_pendapatan_prognosa;
                    $total_verifikasi_pendapatan_bulan_kedua     += $angka_pendapatan_verifikasi;
                }
            }

            $total_pembayaran_bulan_pertama = $pembayaran_bulan_1;
            $total_pembayaran_bulan_kedua   = $pembayaran_bulan_2;

            $total_pelaporan_pendapatan = round(
                $total_pelaporan_pendapatan_bulan_pertama
                + $total_pelaporan_pendapatan_bulan_kedua
                + $total_pelaporan_pendapatan_bulan_ketiga
            );
            $total_verifikasi_pendapatan = round(
                $total_verifikasi_pendapatan_bulan_pertama
                + $total_verifikasi_pendapatan_bulan_kedua
                + $total_verifikasi_pendapatan_bulan_ketiga
            );

            // Pakai rumus originalmu (dipertahankan) + terbilang
            $final_total = round(
                (($total_biaya_pelaporan - $total_pelaporan_pendapatan))
                - (
                    ($total_biaya_pelaporan - $total_pelaporan_biaya_atribusi)
                    - ($verifikasi_biaya_rutin + $verifikasi_biaya_rutin_prognosa)
                    + ($total_pelaporan_biaya_atribusi - ($verifikasi_biaya_atribusi + $verifikasi_biaya_atribusi_prognosa))
                    - ($total_pelaporan_pendapatan - $total_verifikasi_pendapatan)
                )
                - ($total_faktor + $total_pembayaran_bulan_pertama + $total_pembayaran_bulan_kedua)
            );
            $final_total_terbilang = $this->spellId($final_total);

            // Log user (pakai ID)
            UserLog::create([
                'timestamp' => now(),
                'aktifitas' => 'Cetak Berita Acara Verifikasi',
                'modul'     => 'BA Verifikasi',
                'id_user'   => Auth::id(),
            ]);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('berita-acara.verifikasi', [
                // tanggal & terbilang
                'tanggal'                         => $tanggal,
                'bulanKuasa'                      => $bulan_kuasa_terbilang,
                'tanggal_kuasa'                   => $tanggal_kuasa,
                'nomor_verifikasi'                => $no_verifikasi,
                'nomor_verifikasi_2'              => $no_verifikasi_2,
                'triwulan'                        => $triwulan,
                'no_perjanjian_kerja'             => $no_perjanjian_kerja,
                'no_perjanjian_kerja_2'           => $no_perjanjian_kerja_2,
                'tanggal_perjanjian'              => $tanggal_perjanjian,
                'tanggal_perjanjian_terbilang'    => $tanggal_perjanjian_terbilang,
                'tanggal_perjanjian_2_terbilang'  => $tanggal_perjanjian_2_terbilang,
                'bulan_perjanjian_terbilang'      => $bulan_perjanjian_terbilang,
                'bulan_perjanjian_2_terbilang'    => $bulan_perjanjian_2_terbilang,
                'tanggal_perjanjian_2'            => $tanggal_perjanjian_2,

                // identitas & angka
                'tahun_anggaran'                  => $tahun_anggaran,
                'nama_pihak_pertama'              => $nama_pihak_pertama,
                'nama_pihak_kedua'                => $nama_pihak_kedua,

                // faktor koreksi
                'penalti_penyediaan_prasarana'            => $penalti_penyediaan_prasarana,
                'penalti_waktu_tempuh_kiriman_surat'      => $penalti_waktu_tempuh_kiriman_surat,
                'faktur_pengurang'                        => $faktur_pengurang,
                'pembayaran_bulan_1'                      => $pembayaran_bulan_1,
                'pembayaran_bulan_2'                      => $pembayaran_bulan_2,

                // agregat biaya & pendapatan
                'total_pelaporan_biaya_atribusi'          => $total_pelaporan_biaya_atribusi,
                'total_verifikasi_biaya_atribusi'         => $total_verifikasi_biaya_atribusi,
                'total_biaya_pelaporan'                   => $total_biaya_pelaporan,
                'biaya_langsung'                          => $biaya_langsung,
                'total_biaya_verifikasi'                  => $total_biaya_verifikasi,
                'total_pelaporan'                         => $total_pelaporan,
                'total_verifikasi'                        => $total_verifikasi,
                'bulannoverif'                            => $bulannoverif,
                'total_bo_lpu'                            => $total_bo_lpu,
                'total_faktor'                            => $total_faktor,
                'total'                                   => $total,
                'bulan_terakhir'                          => $bulan_terakhir,
                'bulan_pertama'                           => $bulan_pertama,
                'totalpembayaranbulanpertama'             => $total_pembayaran_bulan_pertama,
                'totalpembayaranbulankedua'               => $total_pembayaran_bulan_kedua,
                'totalpelaporanpendapatan'                => $total_pelaporan_pendapatan,
                'totalverifikasipendapatan'               => $total_verifikasi_pendapatan,
                'final_total_terbilang'                   => $final_total_terbilang,
                'pelaporan_biaya_rutin'                   => $pelaporan_biaya_rutin,
                'pelaporan_biaya_rutin_prognosa'          => $pelaporan_biaya_rutin_prognosa,
                'pelaporan_biaya_operasi'                 => $pelaporan_biaya_operasi,
                'verifikasi_biaya_rutin'                  => $verifikasi_biaya_rutin,
                'verifikasi_biaya_rutin_prognosa'         => $verifikasi_biaya_rutin_prognosa,
                'verifikasi_biaya_rutin_operasi'          => $verifikasi_biaya_rutin_operasi,
                'verifikasi_biaya_pendapatan'             => $verifikasi_biaya_pendapatan,
                'pelaporan_biaya_atribusi'                => $pelaporan_biaya_atribusi,
                'pelaporan_biaya_atribusi_prognosa'       => $pelaporan_biaya_atribusi_prognosa,
                'verifikasi_biaya_atribusi'               => $verifikasi_biaya_atribusi,
                'verifikasi_biaya_atribusi_prognosa'      => $verifikasi_biaya_atribusi_prognosa,
                'identity'                                => $user_identity->nama ?? null,
            ]);

            return $pdf->download('berita-acara-verifikasi.pdf');

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
