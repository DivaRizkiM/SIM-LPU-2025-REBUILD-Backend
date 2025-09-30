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

class BeritaAcaraVerifikasiBulananController extends Controller
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

    private static function getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $bulanconvert, $kategori_biaya = null, $lampiran = null)
    {
        $query = VerifikasiBiayaRutinDetail::join('verifikasi_biaya_rutin', 'verifikasi_biaya_rutin.id', '=', 'verifikasi_biaya_rutin_detail.id_verifikasi_biaya_rutin');
        if ($where_user_identity !== null) {
            $query->where($where_user_identity[0], $where_user_identity[1]);
        }

        $query->where('verifikasi_biaya_rutin.tahun', $tahun_anggaran)
            ->where('verifikasi_biaya_rutin_detail.bulan', $bulanconvert);
        if ($kategori_biaya !== null) {
            $query->whereIn('verifikasi_biaya_rutin_detail.kategori_biaya', $kategori_biaya);
        }
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
                'bulan' => 'required|numeric',
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
            $bulan = request()->get('bulan', '');
            $bulanconvert = str_pad($bulan, 2, '0', STR_PAD_LEFT);
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

            $pelaporan_biaya_rutin = $this->getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $bulanconvert, ['BIAYA PEGAWAI', 'BIAYA PEMELIHARAAN', 'BIAYA ADMINISTRASI', 'BIAYA PENYUSUTAN', 'BIAYA OPERASI'])->sum('verifikasi_biaya_rutin_detail.pelaporan');

            $pelaporan_biaya_operasi = $this->getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $bulanconvert, ['BIAYA OPERASI'], 'Y')->sum('verifikasi_biaya_rutin_detail.pelaporan');

            $pelaporan_pendapatan = $this->getPendapatanBulanan($tahun_anggaran, $bulanconvert, false)->sum('produksi_detail.pelaporan');

            $verifikasi_biaya_rutin = $this->getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $bulanconvert)->sum('verifikasi_biaya_rutin_detail.verifikasi');

            $pelaporan_biaya_atribusi = $this->getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $bulanconvert, ['BIAYA OPERASI'])->whereIn('verifikasi_biaya_rutin_detail.id_rekening_biaya', [5102060006, 5102060008, 5102060009, 5102060010, 5102060011])->sum('verifikasi_biaya_rutin_detail.pelaporan');

            $verifikasi_biaya_atribusi = $this->getVerifikasiBulanan($where_user_identity, $tahun_anggaran, $bulanconvert, ['BIAYA OPERASI'], 'N')->sum('verifikasi_biaya_rutin_detail.verifikasi');

            $total_biaya_pelaporan = $pelaporan_biaya_rutin - $pelaporan_biaya_atribusi;
            $total_pelaporan = $total_biaya_pelaporan + $pelaporan_biaya_atribusi - $pelaporan_pendapatan;
            $total_verifikasi = $verifikasi_biaya_rutin + $verifikasi_biaya_atribusi;
            $total_bo_lpu = $total_pelaporan;

            $total_faktor = $penalti_penyediaan_prasarana + $penalti_waktu_tempuh_kiriman_surat + $faktur_pengurang;
            $total = $total_bo_lpu - $total_faktor;
            $terbilangNominal = $this->convertToRupiahTerbilang(round($total * 0.8));
            $terbilangTgl_kuasa = $this->formatTanggal($tanggal_kuasa);
            $bulanKuasa = explode(' ', $tanggal_kuasa_terbilang)[4];
            $tanggal_perjanjian_terbilang = $this->formatTanggal($tanggal_perjanjian);
            $bulanPerjanjian = explode(' ', $tanggal_perjanjian_terbilang)[4];
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
            $namaBulan = $bulanArray[$bulan];
            $tanggal = substr($tanggal_perjanjian, 8, 2) . ' ' . $bulan_perjanjian_terbilang . ' ' . substr($tanggal_perjanjian, 0, 4);
            $userLog=[
                'timestamp' => now(),
                'aktifitas' =>'Cetak Berita Acara Verifikasi Bulanan',
                'modul' => 'BA verifikasi bulanan',
                'id_user' => Auth::user(),
            ];

            $userLog = UserLog::create($userLog);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('berita-acara.verifikasi-bulanan', [
                'tanggal' => $tanggal,
                'bulanKuasa' => $bulanKuasa,
                'tanggal_kuasa' => $tanggal_kuasa,
                'terbilangTgl_kuasa' => $terbilangTgl_kuasa,
                'nama_bulan' => $namaBulan,
                'nomor_verifikasi' => $no_verifikasi,
                'bulan' => $bulan,
                'nomor_perjanjian_kerja' => $no_perjanjian_kerja,
                'nomor_perjanjian_kerja_2' => $no_perjanjian_kerja_2,
                'tanggal_perjanjian' => $tanggal_perjanjian,
                'tanggal_perjanjian_terbilang' => $tanggal_perjanjian_terbilang,
                'terbilangNominal' => $terbilangNominal,

                'bulanPerjanjian' => $bulanPerjanjian,
                'tanggal_perjanjian_2' => $tanggal_perjanjian_2,
                'tahun_anggaran' => $tahun_anggaran,
                'nama_pihak_pertama' => $nama_pihak_pertama,
                'nama_pihak_kedua' => $nama_pihak_kedua,
                'penalti_penyediaan_prasarana' => round($penalti_penyediaan_prasarana ?? 0),
                'penalti_waktu_tempuh_kiriman_surat' => round($penalti_waktu_tempuh_kiriman_surat ?? 0),
                'faktur_pengurang' => round($faktur_pengurang ?? 0),

                'total_biaya_pelaporan' => round($total_biaya_pelaporan ?? 0),
                'total_pelaporan' => round($total_pelaporan ?? 0),
                'total_verifikasi' => round($total_verifikasi ?? 0),
                'total_bo_lpu' => round($total_bo_lpu ?? 0),
                'total_faktor' => round($total_faktor ?? 0),
                'total' => round($total ?? 0),
                'pelaporan_biaya_rutin' => round($pelaporan_biaya_rutin ?? 0),
                'pelaporan_pendapatan' => round($pelaporan_pendapatan ?? 0),
                'pelaporan_biaya_operasi' => round($pelaporan_biaya_operasi ?? 0),
                'verifikasi_biaya_rutin' => round($verifikasi_biaya_rutin ?? 0),
                'pelaporan_biaya_atribusi' => round($pelaporan_biaya_atribusi ?? 0),
                'verifikasi_biaya_atribusi' => round($verifikasi_biaya_atribusi ?? 0),
                'identity' => $user_identity->nama ?? null,
            ]);
            return $pdf->download('berita-acara-verifikasi-bulanan.pdf');

        } catch (\Exception $e) {
            // dd($e);
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }

    }

}
