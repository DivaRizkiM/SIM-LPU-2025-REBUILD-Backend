<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request as GzReq;
use Illuminate\Support\Facades\DB;

class ProcessKpcChunkJob implements ShouldQueue {
  public function __construct(public array $nopends, public int $logId) {}

  public function handle() {
    $client = new Client(['base_uri' => config('services.pos.base_uri'), 'timeout' => 20]);

    $responses = [];
    $requests = function ($nopends) {
      foreach ($nopends as $np) {
        // ApiController kamu bisa diganti signer middleware/gateway sendiri;
        // di sini contoh langsung ke endpoint profil_kpc?nopend=...
        yield new GzReq('GET', "/pso/1.0.0/data/profil_kpc?nopend={$np}");
      }
    };

    $pool = new Pool($client, $requests($this->nopends), [
      'concurrency' => 12, // sesuaikan limit API & server
      'fulfilled' => function ($res, $idx) use (&$responses) {
        $json = json_decode((string)$res->getBody(), true);
        if (!empty($json['data'])) $responses[] = $json['data'];
      },
      'rejected' => function ($reason, $idx) {
        Log::warning('profil_kpc rejected', ['i' => $idx, 'err' => (string)$reason]);
      },
    ]);

    // jalankan semua request
    $pool->promise()->wait();

    // Normalisasi
    $rows = [];
    $now = now();
    foreach ($responses as $payload) {
      $item = is_array($payload) && array_is_list($payload) ? ($payload[0] ?? null) : $payload;
      if (!$item) continue;

      $rows[] = [
        'id'                      => $item['ID_KPC']          ?? $item['id'] ?? null,
        'id_regional'             => $item['Regional']        ?? null,
        'id_kprk'                 => $item['ID_KPRK']         ?? null,
        'nomor_dirian'            => $item['NomorDirian']     ?? null,
        'nama'                    => $item['Nama_KPC']        ?? $item['nama'] ?? null,
        'jenis_kantor'            => $item['Jenis_KPC']       ?? null,
        'alamat'                  => $item['Alamat']          ?? null,
        'koordinat_longitude'     => $item['Longitude']       ?? null,
        'koordinat_latitude'      => $item['Latitude']        ?? null,
        'nomor_telpon'            => $item['Nomor_Telp']      ?? null,
        'nomor_fax'               => $item['Nomor_fax']       ?? null,
        'id_provinsi'             => $item['Provinsi']        ?? null,
        'id_kabupaten_kota'       => $item['Kabupaten_Kota']  ?? null,
        'id_kecamatan'            => $item['Kecamatan']       ?? null,
        'id_kelurahan'            => $item['Kelurahan']       ?? null,
        'tipe_kantor'             => $item['Status_Gedung_Kantor'] ?? null,
        'jam_kerja_senin_kamis'   => $item['JamKerjaSeninKamis']   ?? null,
        'jam_kerja_jumat'         => $item['JamKerjaJumat']        ?? null,
        'jam_kerja_sabtu'         => $item['JamKerjaSabtu']        ?? null,
        'frekuensi_antar_ke_alamat'       => $item['FrekuensiAntarKeAlamat']  ?? null,
        'frekuensi_antar_ke_dari_kprk'    => $item['FrekuensiKirimDariKeKprk']?? null,
        'jumlah_tenaga_kontrak'   => $item['JumlahTenagaKontrak'] ?? null,
        'kondisi_gedung'          => $item['KondisiGedung']       ?? null,
        'fasilitas_publik_dalam'  => $item['FasilitasPublikDalamKantor'] ?? null,
        'fasilitas_publik_halaman'=> $item['FasilitasPublikLuarKantor']  ?? null,
        'lingkungan_kantor'       => $item['LingkunganKantor']   ?? null,
        'lingkungan_sekitar_kantor'=> $item['LingkunganSekitarKantor'] ?? null,
        'jarak_ke_kprk'           => $item['Jarak'] ?? null,
        'tgl_sinkronisasi'        => $now,
        'tgl_update'              => $now,
      ];
    }

    // Upsert per 500 & update progress setelah tiap chunk
    $processed = 0;
    foreach (array_chunk($rows, 500) as $chunk) {
      DB::table('kpc')->upsert(
        $chunk,
        ['id_kpc'],
        ['nopend','id_regional','id_kprk','nomor_dirian','nama','jenis_kantor','alamat',
        'koordinat_longitude','koordinat_latitude','nomor_telpon','nomor_fax',
        'id_provinsi','id_kabupaten_kota','id_kecamatan','id_kelurahan','tipe_kantor',
        'jam_kerja_senin_kamis','jam_kerja_jumat','jam_kerja_sabtu',
        'frekuensi_antar_ke_alamat','frekuensi_antar_ke_dari_kprk',
        'jumlah_tenaga_kontrak','kondisi_gedung','fasilitas_publik_dalam',
        'fasilitas_publik_halaman','lingkungan_kantor','lingkungan_sekitar_kantor',
        'jarak_ke_kprk','tgl_sinkronisasi','tgl_update']
      );
      $processed += count($chunk);

      // update progress
      ApiRequestLog::whereKey($this->logId)->update([
        'successful_records' => DB::raw("successful_records + {$processed}")
      ]);
      $processed = 0;
    }
  }
}