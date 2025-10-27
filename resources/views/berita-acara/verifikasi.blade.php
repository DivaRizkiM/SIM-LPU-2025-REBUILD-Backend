<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>BERITA ACARA VERIFIKASI</title>
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    /* =========================================
       HALAMAN / PRINT
       ========================================= */
    @page {
      size: A4;
      margin: 12mm 14mm;     /* margin luar halaman */
    }
    html, body {
      margin: 0;
      padding: 0;
      background: #fff;
      color: #000;
      font-family: Arial, Helvetica, sans-serif;
      font-size: 12px;
      line-height: 1.35;
      -webkit-print-color-adjust: exact;
              print-color-adjust: exact;
    }

    /* wadah halaman (sisakan ruang tanda tangan di bawah) */
    .page {
      width: 210mm;
      margin: 0 auto;
      padding: 0 0 50mm 0;   /* ruang untuk area tanda tangan (besar) */
    }

    /* lebar konten disetel 600px mengikuti template awalmu */
    .container { max-width: 600px; margin: 0 auto; }

    /* =========================================
       KOP SURAT / HEADER
       ========================================= */
    .kop {
      padding-top: 10mm;             /* tambah ruang dari tepi atas */
      margin-bottom: 4mm;
    }
    .title { text-align: center; font-size: 12px; font-weight: 700; margin: 2px 0; }
    .divider {
      border-bottom: 1px solid #000;     /* garis kop surat (tebal) */
      margin: 6mm 0 3mm;                 /* jarak atas-bawah */
    }

    /* =========================================
       UTILITAS LAYOUT
       ========================================= */
    p { margin: 6px 0; }
    .mt-6 { margin-top: 6px; } .mt-8 { margin-top: 8px; } .mt-10{ margin-top:10px; }
    .mt-12{ margin-top:12px; } .mt-15{ margin-top:15px; } .mt-20{ margin-top:20px; }
    .ml-25{ margin-left:25px; } .ml-40{ margin-left:40px; }
    .w-93{ width:93%; } .w-90{ width:90%; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    .table-compact td { padding: 0; }      /* hemat ruang */
    .no-break { page-break-inside: avoid; break-inside: avoid; }

    /* =========================================
       AREA TANDA TANGAN (FOOTER BESAR, FIXED)
       ========================================= */
    .signature-footer {
      position: fixed;
      left: 14mm; right: 14mm;
      bottom: 17mm;                         /* sejajar margin bawah @page */
    }
    .signature-wrap {
      width: 100%;
      border-top: 1px solid #000;           /* garis pemisah dari konten */
      padding-top: 8mm;                     /* ruang atas area TTD */
    }
    .sig-col {
      width: 50%;
      text-align: center;
      padding: 0 8px;
    }
    .sig-role  { font-size: 12px; }
    .sig-jab   { font-size: 12px; margin-bottom: 24mm; }  /* tinggi area tanda tangan */
    .sig-nama  { font-size: 12px; font-weight: 600; text-decoration: underline; }

    /* =========================================
       PENYESUAI SPASI BLOK AGAR MUAT 1 HALAMAN
       ========================================= */
    .intro, .blok1, .blok2, .blok3, .faktor, .pernyataan, .nominal, .penutup {
      margin-bottom: 6px;
    }
    /* daftar poin awal (1 & 2) dibuat tabel kecil agar rapat */
    .list-awal td:first-child { width: 18px; }
    .list-awal td { font-size: 12px; }

  </style>
</head>
<body>
  <div class="page">
    <div class="container">
      <!-- ===== KOP SURAT ===== -->
      <div class="kop">
        <div class="title">BERITA ACARA VERIFIKASI</div>
        <div class="title">PENYELENGARAAN LAYANAN POS UNIVERSAL {{$identity}}</div>
        <div class="title">TRIWULAN {{ $triwulan }} TAHUN ANGGARAN {{ $tahun_anggaran }}</div>
        <div class="divider"></div>
      </div>

      <!-- ===== PARAGRAF PEMBUKA ===== -->
      <div class="intro no-break">
        <p style="text-align: justify;">
          &nbsp; &nbsp; &nbsp; &nbsp; Pada hari ini {{ $tanggal_kuasa_terbilang }}, Kuasa Pengguna Anggaran Subsidi Operasional Layanan Pos Universal dan Direktur Utama PT Pos Indonesia (Persero) selaku pihak yang diverifikasi, telah menerima :
        </p>
        <table class="list-awal mt-10" style="width:auto;">
          <tr>
            <td>1.</td>
            <td>Berita Acara Pelaksanaan Tindak Lanjut Hasil Audit Internal PT Pos Indonesia ( Persero ) Nomor : {{ $nomor_verifikasi_2 }} Tanggal {{ substr($tanggal_kuasa, 8, 2) }} {{ $bulanKuasa }} {{ substr($tanggal_kuasa, 0, 4) }}</td>
          </tr>
          <tr>
            <td>2.</td>
            <td>Hasil Verifikasi Penyelenggaraan Layanan Pos Universal Triwulan {{$triwulan }} Tahun Anggaran {{$tahun_anggaran }} Nomor: {{$nomor_verifikasi }}/DJPPI.2/PI.01.01/{{ $bulannoverif }}/{{$tahun_anggaran }} Tanggal {{ substr($tanggal_perjanjian_2, 8, 2) }} {{ $bulan_perjanjian_2_terbilang }} {{ substr($tanggal_perjanjian_2, 0, 4) }}</td>
          </tr>
        </table>
      </div>

      <!-- ===== 1. SO LPU Berdasarkan Laporan ===== -->
      <div class="blok1">
        <p>&nbsp; &nbsp; &nbsp; &nbsp; 1. SO LPU Berdasarkan Laporan</p>
        <table class="ml-40 w-90 table-compact">
          <tr><td>a.</td><td>Biaya Langsung</td><td style="width:10px;"></td><td>:</td><td>Rp.</td><td style="text-align:right;">{{ number_format($biaya_langsung, 0, ',', '.') }}</td></tr>
          <tr><td>b.</td><td>Biaya Atribusi</td><td></td><td>:</td><td>Rp.</td><td style="text-align:right;">{{ number_format($total_pelaporan_biaya_atribusi, 0, ',', '.') }}</td></tr>
          <tr><td>c.</td><td>Pendapatan</td><td></td><td>:</td><td>Rp.</td><td style="text-align:right;">{{ number_format($totalpelaporanpendapatan, 0, ',', '.') }}</td></tr>
          <tr><td>d.</td><td>Total SO LPU Berdasarkan Laporan ( a + b - c)</td><td></td><td>:</td><td>Rp.</td>
            <td style="text-align:right;">{{ number_format(round($total_biaya_pelaporan - $totalpelaporanpendapatan), 0, ',', '.') }}</td></tr>
        </table>
      </div>

      <!-- ===== 2. Koreksi Biaya Hasil Verifikasi ===== -->
      <div class="blok2">
        <p>&nbsp; &nbsp; &nbsp; &nbsp; 2. Koreksi Biaya Hasil Verifikasi</p>
        <table class="ml-40 w-90 table-compact">
          <tr><td>a.</td><td>Biaya Langsung</td><td></td><td>:</td><td>Rp.</td>
            <td style="text-align:right;">{{ number_format(round(($biaya_langsung - $total_biaya_verifikasi)), 0, ',', '.') }}</td></tr>
          <tr><td>b.</td><td>Biaya Atribusi</td><td></td><td>:</td><td>Rp.</td>
            <td style="text-align:right;">{{ number_format($total_pelaporan_biaya_atribusi - $total_verifikasi_biaya_atribusi, 0, ',', '.') }}</td></tr>
          <tr><td>c.</td><td>Pendapatan</td><td></td><td>:</td><td>Rp.</td>
            <td style="text-align:right;">{{ number_format($totalpelaporanpendapatan - $totalverifikasipendapatan, 0, ',', '.') }}</td></tr>
          <tr><td>d.</td><td>Total SO LPU Hasil Koreksi ( a + b - c)</td><td></td><td>:</td><td>Rp.</td>
            <td style="text-align:right;">{{ number_format(round(($biaya_langsung - $total_biaya_verifikasi) + ($total_pelaporan_biaya_atribusi - $total_verifikasi_biaya_atribusi) - ($totalpelaporanpendapatan - $totalverifikasipendapatan)), 0, ',', '.') }}</td></tr>
        </table>
      </div>

      <!-- ===== 3. Total SO LPU & Faktor Pengurang ===== -->
      <div class="blok3">
        <table class="ml-25 w-93 table-compact">
          <tr>
            <td style="width:16px;">3.</td>
            <td>Total SO LPU Triwulan {{$triwulan }} Tahun Anggaran {{$tahun_anggaran }} (1.d - 2.d)</td>
            <td style="width:10px;">:</td>
            <td style="width:22px;">Rp.</td>
            <td style="text-align:right;">
              {{ number_format(round(($total_biaya_pelaporan - $totalpelaporanpendapatan) - (($biaya_langsung - $total_biaya_verifikasi) + ($total_pelaporan_biaya_atribusi - $total_verifikasi_biaya_atribusi) - ($totalpelaporanpendapatan - $totalverifikasipendapatan))), 0, ',', '.') }}
            </td>
          </tr>
        </table>
      </div>

      <div class="faktor">
        <p class="mt-8" style="text-align: justify;">
          &nbsp; &nbsp; &nbsp; &nbsp; Namun ditemukenali faktor pengurang pembayaran Dana Penyelenggaraan Layanan Pos Universal sebagai berikut :
        </p>
        <table class="ml-25 w-93 table-compact">
          <tr><td>a.</td><td>Penalti penyediaan prasarana</td><td style="width:10px;">:</td><td style="width:22px;">Rp.</td><td style="text-align:right;">{{ number_format($penalti_penyediaan_prasarana, 0, ',', '.') }}</td></tr>
          <tr><td>b.</td><td>Penalti waktu tempuh kiriman surat</td><td>:</td><td>Rp.</td><td style="text-align:right;">{{ number_format($penalti_waktu_tempuh_kiriman_surat, 0, ',', '.') }}</td></tr>
          <tr><td>c.</td><td>Faktor pengurang atas pembayaran 80%</td><td>:</td><td>Rp.</td><td style="text-align:right;">{{ number_format($totalpembayaranbulanpertama + $totalpembayaranbulankedua, 0, ',', '.') }}</td></tr>

          @php
            $bulanArray = [1 => 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            $pembayaranBulan = [
              $bulan_pertama => $totalpembayaranbulanpertama ?? 0,
              $bulan_pertama + 1 => $totalpembayaranbulankedua ?? 0,
              $bulan_terakhir => $totalpembayaranbulanketiga ?? 0,
            ];
          @endphp
          @for ($i = $bulan_pertama; $i <= $bulan_terakhir; $i++)
            @php $namaBulan = $bulanArray[$i]; $p=$pembayaranBulan[$i] ?? 0; @endphp
            @if($p > 0)
              <tr><td></td><td style="padding-left:30px;">{{ $namaBulan }}</td><td>:</td><td>Rp.</td><td style="text-align:right;">{{ number_format($p, 0, ',', '.') }}</td></tr>
            @endif
          @endfor

          <tr><td>d.</td><td>Jumlah Faktor Pengurang</td><td>:</td><td>Rp.</td>
            <td style="text-align:right;">{{ number_format($total_faktor + $totalpembayaranbulankedua + $totalpembayaranbulanpertama, 0, ',', '.') }}</td></tr>
        </table>
      </div>

      <!-- ===== Pernyataan ===== -->
      <div class="pernyataan">
        <p style="text-align: justify;">
          &nbsp; &nbsp; &nbsp; &nbsp; Kuasa Pengguna Anggaran Subsidi Operasional Layanan Pos Universal dan Direktur Utama PT Pos Indonesia (Persero) menyatakan Penyelenggaraan Layanan Pos Universal Triwulan {{$triwulan }} Tahun Anggaran {{$tahun_anggaran }} telah dilaksanakan sesuai dengan Perjanjian Kerja Nomor : {{$no_perjanjian_kerja }} dan Nomor {{$no_perjanjian_kerja_2 }} tanggal {{ $tanggal }} jo Adendum Perjanjian Kerja Nomor : 3124/DJPP1.2/HK.04.02/11/2023 dan Nomor PKS293/DIRUT/1123 tanggal 14 November 2023, sehingga dapat dibayarkan Dana Penyelenggaraan Layanan Pos Universal untuk Triwulan {{$triwulan }} Tahun Anggaran {{$tahun_anggaran }} sebesar:
        </p>
      </div>

      <!-- ===== Nominal Final ===== -->
      <div class="nominal">
        <table class="ml-25 w-93 table-compact">
          <tr>
            <td style="width:16px;">a.</td><td>SO LPU Berdasarkan Hasil Verifikasi</td><td style="width:10px;">:</td><td style="width:22px;">Rp.</td>
            <td style="text-align:right;">
              {{ number_format(round(($total_biaya_pelaporan - $totalpelaporanpendapatan)) - (($total_biaya_pelaporan - $total_pelaporan_biaya_atribusi) - ($verifikasi_biaya_rutin + $verifikasi_biaya_rutin_prognosa) + ($total_pelaporan_biaya_atribusi - ($verifikasi_biaya_atribusi + $verifikasi_biaya_atribusi_prognosa)) - ($totalpelaporanpendapatan - $totalverifikasipendapatan)), 0, ',', '.') }}
            </td>
          </tr>
          <tr>
            <td>b.</td><td>Faktor Pengurang</td><td>:</td><td>Rp.</td>
            <td style="text-align:right;">{{ number_format($total_faktor + $totalpembayaranbulanpertama + $totalpembayaranbulankedua, 0, ',', '.') }}</td>
          </tr>
          <tr>
            <td>c.</td><td>SO LPU dibayarkan</td><td>:</td><td>Rp.</td>
            <td style="text-align:right;">
              {{ number_format(round((($total_biaya_pelaporan - $totalpelaporanpendapatan)) - (($total_biaya_pelaporan - $total_pelaporan_biaya_atribusi) - ($verifikasi_biaya_rutin + $verifikasi_biaya_rutin_prognosa) + ($total_pelaporan_biaya_atribusi - ($verifikasi_biaya_atribusi + $verifikasi_biaya_atribusi_prognosa)) - ($totalpelaporanpendapatan - $totalverifikasipendapatan)) - ($total_faktor + $totalpembayaranbulanpertama + $totalpembayaranbulankedua)), 0, ',', '.') }}
            </td>
          </tr>
        </table>
        <p class="mt-10" style="text-align:center;"><strong>{{ $final_total_terbilang }} Rupiah</strong></p>
      </div>

      <div class="penutup">
        <p style="text-align: justify;">
          &nbsp; &nbsp; &nbsp; &nbsp; Demikian Berita Acara ini dibuat dalam dua rangkap untuk digunakan sebagai persyaratan pembayaran Dana Penyelenggaraan Layanan Pos Universal Triwulan {{$triwulan }} Tahun {{$tahun_anggaran }}.
        </p>
      </div>
    </div>

    <!-- ===== TANDA TANGAN: FOOTER BESAR & TETAP DI BAWAH ===== -->
    <div class="signature-footer no-break">
      <div class="">
        <table>
          <tr>
            <td class="sig-col">
              <div class="sig-role">PIHAK KEDUA</div>
              <div class="sig-jab">Direktur Utama</div>
              <div class="">{{$nama_pihak_kedua}}</div>
            </td>
            <td class="sig-col">
              <div class="sig-role">PIHAK PERTAMA</div>
              <div class="sig-jab">Kuasa Pengguna Anggaran</div>
              <div class="">{{$nama_pihak_pertama}}</div>
            </td>
          </tr>
        </table>
      </div>
    </div>

  </div>
</body>
</html>
