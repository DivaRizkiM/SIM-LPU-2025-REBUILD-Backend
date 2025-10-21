<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use App\Models\Kpc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

class FetchKPCProfilesChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [5, 10, 20, 40, 60]; // eksponensial

    public function __construct(
        protected array $nopends,
        protected string $endpointProfile,
        protected string $userAgent,
        protected int $apiRequestLogId
    ) {}

    public function handle()
    {
        $api = new ApiController();
        $rows = [];

        foreach (array_filter($nopends = $this->nopends) as $nopend) {
            $req = request()->duplicate();
            $req->merge(['end_point' => $this->endpointProfile.'?nopend='.$nopend]);

            $resp = $api->makeRequest($req);
            $profiles = $resp['data'] ?? [];

            foreach ($profiles as $d) {
                // map data → row KPC
                $rows[] = [
                    'id'                       => $d['ID_KPC'],
                    'id_regional'              => $d['Regional'],
                    'id_kprk'                  => $d['ID_KPRK'],
                    'nomor_dirian'             => $d['NomorDirian'],
                    'nama'                     => $d['Nama_KPC'],
                    'jenis_kantor'             => $d['Jenis_KPC'],
                    'alamat'                   => $d['Alamat'],
                    'koordinat_longitude'      => $d['Longitude'],
                    'koordinat_latitude'       => $d['Latitude'],
                    'nomor_telpon'             => $d['Nomor_Telp'],
                    'nomor_fax'                => $d['Nomor_fax'],
                    'id_provinsi'              => $d['Provinsi'],
                    'id_kabupaten_kota'        => $d['Kabupaten_Kota'],
                    'id_kecamatan'             => $d['Kecamatan'],
                    'id_kelurahan'             => $d['Kelurahan'],
                    'tipe_kantor'              => $d['Status_Gedung_Kantor'],
                    'jam_kerja_senin_kamis'    => $d['JamKerjaSeninKamis'],
                    'jam_kerja_jumat'          => $d['JamKerjaJumat'],
                    'jam_kerja_sabtu'          => $d['JamKerjaSabtu'],
                    'frekuensi_antar_ke_alamat'=> $d['FrekuensiAntarKeAlamat'],
                    'frekuensi_antar_ke_dari_kprk'=> $d['FrekuensiKirimDariKeKprk'],
                    'jumlah_tenaga_kontrak'    => $d['JumlahTenagaKontrak'],
                    'kondisi_gedung'           => $d['KondisiGedung'],
                    'fasilitas_publik_dalam'   => $d['FasilitasPublikDalamKantor'],
                    'fasilitas_publik_halaman' => $d['FasilitasPublikLuarKantor'],
                    'lingkungan_kantor'        => $d['LingkunganKantor'],
                    'lingkungan_sekitar_kantor'=> $d['LingkunganSekitarKantor'],
                    'tgl_sinkronisasi'         => now(),
                    'tgl_update'               => now(),
                    'jarak_ke_kprk'            => $d['Jarak'],
                ];
            }
        }

        if (!$rows) return;

        // UP SERT BULK (1 query per chunk)
        DB::transaction(function () use ($rows) {
            // kolom yang di-update kalau "id" bentrok
            $updateCols = array_keys($rows[0]);
            // hapus "id" dari kolom update
            $updateCols = array_values(array_diff($updateCols, ['id']));

            DB::table('kpc')->upsert($rows, ['id'], $updateCols);
        });

        // update progress kompak
        ApiRequestLog::whereKey($this->apiRequestLogId)
            ->incrementEach([
                'successful_records' => count($rows),
                'total_records' => count($rows),
            ], 1);
    }
}

