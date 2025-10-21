<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiRequestLog;
use App\Models\Kpc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessKpcChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $nopends,
        public int $logId,
        public ?string $userAgent = null
    ) {}

    public function handle()
    {
        $api = new ApiController();
        $now = now();
        $rows = [];

        foreach ($this->nopends as $nopend) {
            try {
                $resp = $api->makeRequest(HttpRequest::create('/', 'GET', [
                    'end_point' => 'profil_kpc',
                    'nopend'    => $nopend,
                ]));

                $data = $resp['data'] ?? null;
                if (!$data) continue;

                // normalisasi: array assoc / array list
                $it = is_array($data) && array_is_list($data) ? ($data[0] ?? null) : $data;
                if (!$it) continue;

                $rows[] = [
                    'id'                 => $it['ID_KPC']             ?? null,
                    'id_regional'            => $it['Regional']           ?? null,
                    'id_kprk'                => $it['ID_KPRK']            ?? null,
                    'nomor_dirian'           => $it['NomorDirian']        ?? null,
                    'nama'                   => $it['Nama_KPC']           ?? null,
                    'jenis_kantor'           => $it['Jenis_KPC']          ?? null,
                    'alamat'                 => $it['Alamat']             ?? null,
                    'koordinat_longitude'    => $it['Longitude']          ?? null,
                    'koordinat_latitude'     => $it['Latitude']           ?? null,
                    'nomor_telpon'           => $it['Nomor_Telp']         ?? null,
                    'nomor_fax'              => $it['Nomor_fax']          ?? null,
                    'id_provinsi'            => $it['Provinsi']           ?? null,
                    'id_kabupaten_kota'      => $it['Kabupaten_Kota']     ?? null,
                    'id_kecamatan'           => $it['Kecamatan']          ?? null,
                    'id_kelurahan'           => $it['Kelurahan']          ?? null,
                    'tipe_kantor'            => $it['Status_Gedung_Kantor'] ?? null,
                    'jam_kerja_senin_kamis'  => $it['JamKerjaSeninKamis'] ?? null,
                    'jam_kerja_jumat'        => $it['JamKerjaJumat']      ?? null,
                    'jam_kerja_sabtu'        => $it['JamKerjaSabtu']      ?? null,
                    'frekuensi_antar_ke_alamat'    => $it['FrekuensiAntarKeAlamat']   ?? null,
                    'frekuensi_antar_ke_dari_kprk' => $it['FrekuensiKirimDariKeKprk'] ?? null,
                    'jumlah_tenaga_kontrak'  => $it['JumlahTenagaKontrak'] ?? null,
                    'kondisi_gedung'         => $it['KondisiGedung']       ?? null,
                    'fasilitas_publik_dalam' => $it['FasilitasPublikDalamKantor'] ?? null,
                    'fasilitas_publik_halaman'=> $it['FasilitasPublikLuarKantor']  ?? null,
                    'lingkungan_kantor'      => $it['LingkunganKantor']    ?? null,
                    'lingkungan_sekitar_kantor'=> $it['LingkunganSekitarKantor'] ?? null,
                    'jarak_ke_kprk'          => $it['Jarak'] ?? null,
                    'tgl_sinkronisasi'       => $now,
                    'tgl_update'             => $now,
                ];
            } catch (\Throwable $e) {
                Log::warning('profil_kpc gagal', ['nopend' => $nopend, 'err' => $e->getMessage()]);
            }
        }

        // upsert per 500 & progress
        $affected = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('kpc')->upsert(
                $chunk,
                ['id'], // atau ['nopend'] kalau itu yg unik
                [
                    'id_regional','id_kprk','nomor_dirian','nama','jenis_kantor','alamat',
                    'koordinat_longitude','koordinat_latitude','nomor_telpon','nomor_fax',
                    'id_provinsi','id_kabupaten_kota','id_kecamatan','id_kelurahan','tipe_kantor',
                    'jam_kerja_senin_kamis','jam_kerja_jumat','jam_kerja_sabtu',
                    'frekuensi_antar_ke_alamat','frekuensi_antar_ke_dari_kprk',
                    'jumlah_tenaga_kontrak','kondisi_gedung','fasilitas_publik_dalam',
                    'fasilitas_publik_halaman','lingkungan_kantor','lingkungan_sekitar_kantor',
                    'jarak_ke_kprk','tgl_sinkronisasi','tgl_update'
                ]
            );
            $affected += count($chunk);

            // naikkan progress di log
            ApiRequestLog::whereKey($this->logId)
                ->increment('successful_records', count($chunk));
        }

        // bila tidak ada row, tandai sub-batch tidak ada data (opsional)
        if ($affected === 0) {
            ApiRequestLog::whereKey($this->logId)->update(['status' => 'no_data']);
        } else {
            ApiRequestLog::whereKey($this->logId)->update(['status' => 'Memproses Batch']);
        }
    }
}
