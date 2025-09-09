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
use Symfony\Component\HttpFoundation\Response;

class BeritaAcaraVerifikasiController extends Controller
{
    private function convertToRupiahTerbilang($nilai)
    {
        $formatter = new NumberFormatter('id_ID', NumberFormatter::SPELLOUT);
        return ucwords($formatter->format($nilai));
    }
    public static function formatTanggal($tanggal)
    {
        $hari = [
            1 => 'Senin',
            'Selasa',
            'Rabu',
            'Kamis',
            'Jumat',
            'Sabtu',
            'Minggu',
        ];

        $bulanArray = [
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember',
        ];

        $tanggalCarbon = Carbon::createFromFormat('Y-m-d', $tanggal);
        $hariIni = $hari[$tanggalCarbon->dayOfWeek + 1];

        $formatter = new NumberFormatter('id_ID', NumberFormatter::SPELLOUT);
        $tanggal_day = ucwords($formatter->format($tanggalCarbon->day));
        $tahun_anggaran = ucwords($formatter->format($tanggalCarbon->year));

        $namaBulan = $bulanArray[$tanggalCarbon->month];

        return $hariIni . ' tanggal ' . $tanggal_day . ' bulan ' . $namaBulan . ' tahun ' . $tahun_anggaran;
    }
    public function getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, $kategori_biaya, $lampiran = null)
    {
        $query = VerifikasiBiayaRutinDetail::join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin');
        if ($where_user_identity !== null) {
            $query->where($where_user_identity[0], $where_user_identity[1]);
        }

        $query->where('tahun', $tahun_anggaran)
            ->where('triwulan', $triwulan)
            ->whereIn('kategori_biaya', $kategori_biaya);

        if ($lampiran !== null) {
            $query->where('lampiran', $lampiran);
        }

        return $query;
    }
    private static function getPendapatanBulanan($tahun_anggaran, $bulanconvert, $notIn = false)
    {
        $query = Produksi::join('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
            ->where('produksi.tahun_anggaran', $tahun_anggaran)
            ->where('produksi_detail.nama_bulan', $bulanconvert);

        if ($notIn) {
            $query->whereNotIn('produksi_detail.nama_bulan', [10]);
        }

        return $query;
    }

    private static function getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $bulanconvert, $kategori_biaya, $lampiran = null)
    {
        $query = VerifikasiBiayaRutinDetail::join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin');
        if ($where_user_identity !== null) {
            $query->where($where_user_identity[0], $where_user_identity[1]);
        }

        $query->where('verifikasi_biaya_rutin.tahun', $tahun_anggaran)
            ->where('verifikasi_biaya_rutin_detail.bulan', $bulanconvert)
            ->whereIn('verifikasi_biaya_rutin_detail.kategori_biaya', $kategori_biaya);

        if ($lampiran !== null) {
            $query->where('lampiran', $lampiran);
        }

        return $query;
    }

    public function index(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'tanggal_kuasa' => 'required|date',
                'no_verifikasi' => 'required',
                'no_verifikasi_2' => 'required',
                'triwulan' => 'required|numeric|in:1,2,3,4',
                'no_perjanjian_kerja' => 'required',
                'no_perjanjian_kerja_2' => 'required',
                'tanggal_perjanjian' => 'required|date',
                'tanggal_perjanjian_2' => 'required|date',
                'tahun_anggaran' => 'required|numeric',
                'nama_pihak_pertama' => 'nullable|string',
                'nama_pihak_kedua' => 'nullable|string',

                'penalti_penyediaan_prasarana' => 'nullable',
                'penalti_waktu_tempuh_kiriman_surat' => 'nullable',
                'faktur_pengurang' => 'nullable',
                'pembayaran_bulan_1' => 'nullable',
                'pembayaran_bulan_2' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors(),
                    'error_code' => 'INPUT_VALIDATION_ERROR',
                ], 422);
            }

            $tanggal_kuasa = request()->get('tanggal_kuasa', '');
            $no_verifikasi = request()->get('no_verifikasi', '');
            $no_verifikasi_2 = request()->get('no_verifikasi_2', '');
            $triwulan = request()->get('triwulan', '');
            $no_perjanjian_kerja = request()->get('no_perjanjian_kerja', '');
            $no_perjanjian_kerja_2 = request()->get('no_perjanjian_kerja_2', '');
            $tanggal_perjanjian = request()->get('tanggal_perjanjian', '');
            $tanggal_perjanjian_2 = request()->get('tanggal_perjanjian_2', '');
            $tahun_anggaran = request()->get('tahun_anggaran', '');
            $nama_pihak_pertama = request()->get('nama_pihak_pertama', 'GUNAWAN HUTAGALUNG');
            $nama_pihak_kedua = request()->get('nama_pihak_kedua', 'FAIZAL ROCHMAD DJOEMADI');
            $penalti_penyediaan_prasarana = request()->get('penalti_penyediaan_prasarana', 0);
            $penalti_waktu_tempuh_kiriman_surat = request()->get('penalti_waktu_tempuh_kiriman_surat', 0);
            $faktur_pengurang = request()->get('faktur_pengurang', 0);
            $pembayaran_bulan_1 = request()->get('pembayaran_bulan_1', 0);
            $pembayaran_bulan_2 = request()->get('pembayaran_bulan_2', 0);

            $bulan_terakhir = $triwulan == 1 ? 3 : ($triwulan == 2 ? 6 : ($triwulan == 3 ? 9 : 12));
            $bulan_pertama = $triwulan == 1 ? 1 : ($triwulan == 2 ? 4 : ($triwulan == 3 ? 7 : 10));

            $tanggal_kuasa_terbilang = $this->formatTanggal($tanggal_kuasa);
            $tanggal_perjanjian_terbilang = $this->formatTanggal($tanggal_perjanjian);
            $tanggal_perjanjian_2_terbilang = $this->formatTanggal($tanggal_perjanjian_2);
            $bulan_kuasa_terbilang = explode(' ', $tanggal_kuasa_terbilang)[4];
            $bulan_perjanjian_terbilang = explode(' ', $tanggal_perjanjian_terbilang)[4];
            $bulan_perjanjian_2_terbilang = explode(' ', $tanggal_perjanjian_2_terbilang)[4];
            $tanggal = substr($tanggal_perjanjian, 8, 2) . ' ' . $bulan_perjanjian_terbilang . ' ' . substr($tanggal_kuasa, 0, 4);
            // dd($tanggal_perjanjian_2_terbilang);
            $user = Auth::user();
            $user_identity = null;
            $where_user_identity = null; // Menetapkan kondisi not null dengan benar

            if ($user->id_kpc) {
                $user_identity = Kpc::find($user->id_kpc);
                $where_user_identity = ['verifikasi_biaya_rutin.id_kpc', $user_identity->nomor_dirian];

            } elseif ($user->id_kprk) {
                $user_identity = Kprk::find($user->id_kprk);
                $where_user_identity = ['verifikasi_biaya_rutin.id_kprk', $user_identity->id];

            } elseif ($user->id_regional) {
                $user_identity = Regional::find($user->id_regional);
                $where_user_identity = ['verifikasi_biaya_rutin.regional', $user_identity->id];
            }
            // Penggunaan fungsi getVerifikasi() untuk berbagai kueri
            $pelaporan_biaya_rutin = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA PEGAWAI', 'BIAYA PEMELIHARAAN', 'BIAYA ADMINISTRASI', 'BIAYA PENYUSUTAN', 'BIAYA OPERASI'])->sum('verifikasi_biaya_rutin_detail.pelaporan');

            // dd($pelaporan_biaya_rutin);
            $pelaporan_biaya_rutin_prognosa = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA PEGAWAI', 'BIAYA PEMELIHARAAN', 'BIAYA ADMINISTRASI', 'BIAYA PENYUSUTAN', 'BIAYA OPERASI'])->whereNotIn('verifikasi_biaya_rutin_detail.bulan', [10])->sum('pelaporan_prognosa');

            $pelaporan_biaya_operasi = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'], 'Y')->sum('pelaporan');

            $verifikasi_biaya_rutin = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA PEGAWAI', 'BIAYA PEMELIHARAAN', 'BIAYA ADMINISTRASI', 'BIAYA PENYUSUTAN', 'BIAYA OPERASI'])->whereNotIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', [5102060006, 5102060008, 5102060009, 5102060010, 5102060011])->sum('verifikasi_biaya_rutin_detail.verifikasi');

            $verifikasi_biaya_rutin_prognosa = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA PEGAWAI', 'BIAYA PEMELIHARAAN', 'BIAYA ADMINISTRASI', 'BIAYA PENYUSUTAN', 'BIAYA OPERASI'])->whereNotIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', [5102060006, 5102060008, 5102060009, 5102060010, 5102060011])->whereNotIn('verifikasi_biaya_rutin_detail.bulan', [10])->sum('verifikasi_biaya_rutin_detail.pelaporan_prognosa');

            $verifikasi_biaya_rutin_operasi = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'], 'Y')->sum('verifikasi');

            $pelaporan_biaya_atribusi = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'])->whereIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', [5102060006, 5102060008, 5102060009, 5102060010, 5102060011])->sum('verifikasi_biaya_rutin_detail.pelaporan');

            $pelaporan_biaya_atribusi_prognosa = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'])->whereIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', [5102060006, 5102060008, 5102060009, 5102060010, 5102060011])->whereNotIn('verifikasi_biaya_rutin_detail.bulan', [10])->sum('verifikasi_biaya_rutin_detail.pelaporan_prognosa');

            $verifikasi_biaya_atribusi = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'])->whereIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', [5102060006, 5102060008, 5102060009, 5102060010, 5102060011])->sum('verifikasi_biaya_rutin_detail.verifikasi');

            $verifikasi_biaya_atribusi_prognosa = $this->getVerifikasi($where_user_identity, $tahun_anggaran, $triwulan, ['BIAYA OPERASI'], null)->whereIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', [5102060006, 5102060008, 5102060009, 5102060010, 5102060011])->whereNotIn('verifikasi_biaya_rutin_detail.bulan', [10])->sum('pelaporan_prognosa');

            $verifikasi_biaya_pendapatan = Produksi::join('produksi_detail', 'produksi_detail.id_produksi', '=', 'produksi.id')
                ->where('tahun_anggaran', $tahun_anggaran)
                ->where('triwulan', $triwulan)
                ->sum('produksi_detail.verifikasi');

            $total_pelaporan_biaya_atribusi = $pelaporan_biaya_atribusi + $pelaporan_biaya_atribusi_prognosa;
            $total_verifikasi_biaya_atribusi = $verifikasi_biaya_atribusi + $verifikasi_biaya_atribusi_prognosa;
            $total_biaya_pelaporan = $pelaporan_biaya_rutin + $pelaporan_biaya_rutin_prognosa;
            $total_pelaporan = $total_biaya_pelaporan + $pelaporan_biaya_atribusi;
            $total_verifikasi = $verifikasi_biaya_rutin + $verifikasi_biaya_atribusi;
            $total_biaya_verifikasi =$verifikasi_biaya_rutin + $verifikasi_biaya_rutin_prognosa;
            $bulannoverif = date('m');
            $total_bo_lpu = $total_pelaporan - $total_verifikasi;

            $total_faktor = $penalti_penyediaan_prasarana + $penalti_waktu_tempuh_kiriman_surat + $faktur_pengurang;
            $total = $total_bo_lpu - $total_faktor;

            $total_pelaporan_biaya_bulan_pertama = 0;
            $total_verifikasi_biaya_bulan_pertama = 0;
            $total_pelaporan_pendapatan_bulan_pertama = 0;
            $total_verifikasi_pendapatan_bulan_pertama = 0;

            $total_pelaporan_biaya_bulan_kedua = 0;
            $total_verifikasi_biaya_bulan_kedua = 0;
            $total_pelaporan_pendapatan_bulan_kedua = 0;
            $total_verifikasi_pendapatan_bulan_kedua = 0;

            $total_pelaporan_biaya_bulan_ketiga = 0;
            $total_verifikasi_biaya_bulan_ketiga = 0;
            $total_pelaporan_pendapatan_bulan_ketiga = 0;
            $total_verifikasi_pendapatan_bulan_ketiga = 0;

            for ($i = $bulan_pertama; $i <= $bulan_terakhir; $i++) {
                $bulanconvert = str_pad($i, 2, '0', STR_PAD_LEFT);
                $angka_biaya_rutin = $this->getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $bulanconvert, ['BIAYA PEGAWAI', 'BIAYA PEMELIHARAAN', 'BIAYA ADMINISTRASI', 'BIAYA PENYUSUTAN', 'BIAYA OPERASI'])->sum('verifikasi_biaya_rutin_detail.pelaporan');

                $angka_biaya_operasi = $this->getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $bulanconvert, ['BIAYA OPERASI'], 'Y')->sum('verifikasi_biaya_rutin_detail.pelaporan');

                $angka_biaya_atribusi = $this->getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $bulanconvert, ['BIAYA OPERASI'])->whereIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', [5102060006, 5102060008, 5102060009, 5102060010, 5102060011])->sum('verifikasi_biaya_rutin_detail.pelaporan');

                $angka_pendapatan = $this->getPendapatanBulanan($tahun_anggaran, $bulanconvert, false)->sum('produksi_detail.pelaporan');

                $angka_pendapatan_prognosa = $this->getPendapatanBulanan($tahun_anggaran, $bulanconvert, true)->sum('produksi_detail.pelaporan_prognosa');

                $angka_pendapatan_verifikasi = $this->getPendapatanBulanan($tahun_anggaran, $bulanconvert, false)->sum('produksi_detail.verifikasi');

                $angka_pendapatan_verifikasi_prognosa = $this->getPendapatanBulanan($tahun_anggaran, $bulanconvert, true)->sum('produksi_detail.verifikasi');

                if ($i == $bulan_pertama) {

                    $total_pelaporan_biaya_bulan_pertama += $angka_biaya_rutin + $angka_biaya_operasi + $angka_biaya_atribusi;

                    $total_pelaporan_pendapatan_bulan_pertama += $angka_pendapatan + $angka_pendapatan_prognosa;

                    $total_verifikasi_pendapatan_bulan_pertama += $angka_pendapatan_verifikasi + $angka_pendapatan_verifikasi_prognosa;

                } elseif ($i == $bulan_terakhir) {

                    $total_pelaporan_biaya_bulan_ketiga += $angka_biaya_rutin + $angka_biaya_operasi + $angka_biaya_atribusi;

                    $total_pelaporan_pendapatan_bulan_ketiga += $angka_pendapatan + $angka_pendapatan_prognosa;

                    $total_verifikasi_pendapatan_bulan_ketiga += $angka_pendapatan_verifikasi + $angka_pendapatan_verifikasi_prognosa;

                } else {

                    $total_pelaporan_biaya_bulan_kedua += $angka_biaya_rutin + $angka_biaya_operasi + $angka_biaya_atribusi;

                    $total_pelaporan_pendapatan_bulan_kedua += $angka_pendapatan + $angka_pendapatan_prognosa;

                    $total_verifikasi_pendapatan_bulan_kedua += $angka_pendapatan_verifikasi + $angka_pendapatan_verifikasi_prognosa;
                }
            }

            $total_n_bulan_pertama = $total_pelaporan_biaya_bulan_pertama - $total_pelaporan_pendapatan_bulan_pertama;

            $total_pembayaran_bulan_pertama = $pembayaran_bulan_1;

            $total_bulan_kedua = $total_pelaporan_biaya_bulan_kedua - $total_pelaporan_pendapatan_bulan_kedua;

            $total_pembayaran_bulan_kedua = $pembayaran_bulan_2;

            $total_n_bulan_ketiga = $total_pelaporan_biaya_bulan_ketiga - $total_pelaporan_pendapatan_bulan_ketiga;

            $total_pembayaran_bulan_ketiga = number_format(round($total_n_bulan_pertama * 0.2 + $total_bulan_kedua * 0.2 + $total_n_bulan_ketiga), 0, ',', '.');

            $total_pelaporan_pendapatan = round($total_pelaporan_pendapatan_bulan_pertama + $total_pelaporan_pendapatan_bulan_kedua + $total_pelaporan_pendapatan_bulan_ketiga);

            $total_verifikasi_pendapatan = round($total_verifikasi_pendapatan_bulan_pertama + $total_verifikasi_pendapatan_bulan_kedua + $total_verifikasi_pendapatan_bulan_ketiga);

            $total_berdasarkan_pelaporan = $total_pelaporan;

            $total_berdasarkan_pelaporan_pendapatan = $total_pelaporan_pendapatan;

            $total_berdasarkan_verifikasi_pendapatan = $total_verifikasi_pendapatan;

            $total_deviasi_pendapatan = $total_berdasarkan_pelaporan_pendapatan - $total_berdasarkan_verifikasi_pendapatan;

            $total_berdasarkan_verifikasi = ($verifikasi_biaya_rutin + $verifikasi_biaya_rutin_operasi) + $verifikasi_biaya_atribusi;

            $total_deviasi_biaya_langsung = $total_berdasarkan_pelaporan - $total_berdasarkan_verifikasi;

            $total_angka_deviasi = ($total_biaya_pelaporan - $pelaporan_biaya_atribusi) - ($verifikasi_biaya_rutin - $verifikasi_biaya_atribusi) + ($pelaporan_biaya_atribusi - $verifikasi_biaya_atribusi) - ($total_pelaporan_pendapatan - $total_verifikasi_pendapatan);

            $total_angka_pelaporan = $total_biaya_pelaporan - $total_pelaporan_pendapatan;

            $final_total_terbilang = $this->convertToRupiahTerbilang(round((($total_biaya_pelaporan - $total_pelaporan_pendapatan)) - (($total_biaya_pelaporan - $total_pelaporan_biaya_atribusi) - ($verifikasi_biaya_rutin + $verifikasi_biaya_rutin_prognosa) + ($total_pelaporan_biaya_atribusi - ($verifikasi_biaya_atribusi + $verifikasi_biaya_atribusi_prognosa)) - ($total_pelaporan_pendapatan - $total_verifikasi_pendapatan)) - ($total_faktor + $total_pembayaran_bulan_pertama + $total_pembayaran_bulan_kedua)));
            // dd($final_total_terbilang);
            $tanggal_perjanjian_terbilang = $this->formatTanggal($tanggal_perjanjian);
            $tanggal_perjanjian_2_terbilang = $this->formatTanggal($tanggal_perjanjian_2);
            $bulan_kuasa_terbilang = explode(' ', $tanggal_kuasa_terbilang)[4];
            $bulan_perjanjian_terbilang = explode(' ', $tanggal_perjanjian_terbilang)[4];
            $bulan_perjanjian_2_terbilang = explode(' ', $tanggal_perjanjian_2_terbilang)[4];
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Cetak Berita Acara Verifikasi',
                'modul' => 'BA Verifikasi',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('berita-acara.verifikasi', [
                'tanggal' => $tanggal,
                'bulanKuasa' => $bulan_kuasa_terbilang,
                'tanggal_kuasa' => $tanggal_kuasa,
                'nomor_verifikasi' => $no_verifikasi,
                'nomor_verifikasi_2' => $no_verifikasi_2,
                'triwulan' => $triwulan,
                'no_perjanjian_kerja' => $no_perjanjian_kerja,
                'no_perjanjian_kerja_2' => $no_perjanjian_kerja_2,
                'tanggal_perjanjian' => $tanggal_perjanjian,
                'tanggal_perjanjian_terbilang' => $tanggal_perjanjian_terbilang,
                'tanggal_perjanjian_2_terbilang' => $tanggal_perjanjian_2_terbilang,
                'bulan_perjanjian_terbilang' => $bulan_perjanjian_terbilang,
                'bulan_perjanjian_2_terbilang' => $bulan_perjanjian_2_terbilang,
                'tanggal_perjanjian_2' => $tanggal_perjanjian_2,
                'tahun_anggaran' => $tahun_anggaran,
                'nama_pihak_pertama' => $nama_pihak_pertama,
                'nama_pihak_kedua' => $nama_pihak_kedua,
                'penalti_penyediaan_prasarana' => $penalti_penyediaan_prasarana,
                'penalti_waktu_tempuh_kiriman_surat' => $penalti_waktu_tempuh_kiriman_surat,
                'faktur_pengurang' => $faktur_pengurang,
                'pembayaran_bulan_1' => $pembayaran_bulan_1,
                'pembayaran_bulan_2' => $pembayaran_bulan_2,
                'total_pelaporan_biaya_atribusi' => $total_pelaporan_biaya_atribusi,
                'total_verifikasi_biaya_atribusi' => $total_verifikasi_biaya_atribusi,
                'total_biaya_pelaporan' => $total_biaya_pelaporan,
                'total_biaya_verifikasi' => $total_biaya_verifikasi,
                'total_pelaporan' => $total_pelaporan,
                'total_verifikasi' => $total_verifikasi,
                'bulannoverif' => $bulannoverif,
                'total_bo_lpu' => $total_bo_lpu,
                'total_faktor' => $total_faktor,
                'total' => $total,
                'bulan_terakhir' => $bulan_terakhir,
                'bulan_pertama' => $bulan_pertama,
                'totalnbulanpertama' => $total_n_bulan_pertama,
                'totalpembayaranbulanpertama' => $total_pembayaran_bulan_pertama,
                'totalbulankedua' => $total_pembayaran_bulan_kedua,
                'totalpembayaranbulankedua' => $total_pembayaran_bulan_kedua,
                'totalnbulanketiga' => $total_n_bulan_ketiga,
                'totalpembayaranbulanketiga' => $total_pembayaran_bulan_ketiga,
                'totalpelaporanpendapatan' => $total_pelaporan_pendapatan,
                'totalverifikasipendapatan' => $total_verifikasi_pendapatan,
                'totalberdasarkanpelaporan' => $total_berdasarkan_pelaporan,
                'totalberdasarkanpelaporanpendapatan' => $total_berdasarkan_pelaporan_pendapatan,
                'totalberdasarkanverifikasipendapatan' => $total_berdasarkan_verifikasi_pendapatan,
                'totaldeviasipendapatan' => $total_deviasi_pendapatan,
                'totalberdasarkanverifikasi' => $total_berdasarkan_verifikasi,
                'totaldeviasibiayalangsung' => $total_deviasi_biaya_langsung,
                'totalangkadeviasi' => $total_angka_deviasi,
                'totalangkapelaporan' => $total_angka_pelaporan,
                'final_total_terbilang' => $final_total_terbilang,
                'pelaporan_biaya_rutin' => $pelaporan_biaya_rutin,
                'pelaporan_biaya_rutin_prognosa' => $pelaporan_biaya_rutin_prognosa,
                'pelaporan_biaya_operasi' => $pelaporan_biaya_operasi,
                'verifikasi_biaya_rutin' => $verifikasi_biaya_rutin,
                'verifikasi_biaya_rutin_prognosa' => $verifikasi_biaya_rutin_prognosa,
                'verifikasi_biaya_rutin_operasi' => $verifikasi_biaya_rutin_operasi,
                'verifikasi_biaya_pendapatan' => $verifikasi_biaya_pendapatan,
                'pelaporan_biaya_atribusi' => $pelaporan_biaya_atribusi,
                'pelaporan_biaya_atribusi_prognosa' => $pelaporan_biaya_atribusi_prognosa,
                'verifikasi_biaya_atribusi' => $verifikasi_biaya_atribusi,
                'verifikasi_biaya_atribusi_prognosa' => $verifikasi_biaya_atribusi_prognosa,
                'identity' => $user_identity->nama ?? null,

            ]);
            return $pdf->download('berita-acara-verifikasi.pdf');

        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }

    }

}
