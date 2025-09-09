<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiLog;
use App\Models\Kpc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use Illuminate\Support\Facades\Log;

class ProcessSyncKPCJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $endpoint;
    protected $endpointProfile;
    protected $userAgent;
    public function __construct($endpoint, $endpointProfile, $userAgent)
    {

        $this->endpoint = $endpoint;
        $this->endpointProfile = $endpointProfile;

        $this->userAgent = $userAgent;
    }

    public function handle()
    {
        try {
            $serverIpAddress = gethostbyname(gethostname());
            $agent = new Agent();
            $agent->setUserAgent($this->userAgent);
            $platform = $agent->platform();
            $browser = $agent->browser();
            $platform_request = $platform . '/' . $browser;

            $totalTarget = 0;
            $allFetchedData = [];
            $totalSumber = 0;

            $apiController = new ApiController();
            $urlRequest = $this->endpoint;
            $request = request();
            $request->merge(['end_point' => $urlRequest]);

            $response = $apiController->makeRequest($request);

            $dataKPC = $response['data'] ?? [];
            if (!$dataKPC) {
                return response()->json(['message' => 'Terjadi kesalahan: sync error'], 500);
            }
            $apiRequestLog = ApiRequestLog::create([
                'komponen' => 'KCP',
                'tanggal' => now(),
                'ip_address' => $serverIpAddress,
                'platform_request' => $platform_request,
                'successful_records' => 0,
                'available_records' => 0,
                'total_records' => 0,
                'status' => 'Memuat Data',
            ]);
           $payload = ApiRequestPayloadLog::create([
                'api_request_log_id' => $apiRequestLog->id,
                'payload' => null, // Store the payload as JSON
            ]);

            foreach ($dataKPC as $data) {
                $urlRequest = $this->endpointProfile . '?nopend=' . $data['nopend'];
                $request->merge(['end_point' => $urlRequest]);

                $response = $apiController->makeRequest($request);

                $dataKPC = $response['data'] ?? [];
                foreach ($dataKPC as $data) {
                    if (empty($dataKPC)) {
                        continue;
                    } else {
                        $allFetchedData[] = $data;
                        $totalTarget++;
                    }
                }
            }

            $status = 'on progress';
            if ($allFetchedData == []) {
                $status = 'data tidak tersedia';
            }

            $apiRequestLog->update([    
                'total_records'=> $totalTarget,
                'available_records' => $response['total_data'] ??$totalTarget,
                'status' => $status,
            ]);
            foreach ($allFetchedData as $data) {
                $existingKPC = Kpc::find($data['ID_KPC']);
                $kpcData = [
                    'id' => $data['ID_KPC'],
                    'id_regional' => $data['Regional'],
                    'id_kprk' => $data['ID_KPRK'],
                    'nomor_dirian' => $data['NomorDirian'],
                    'nama' => $data['Nama_KPC'],
                    'jenis_kantor' => $data['Jenis_KPC'],
                    'alamat' => $data['Alamat'],
                    'koordinat_longitude' => $data['Longitude'],
                    'koordinat_latitude' => $data['Latitude'],
                    'nomor_telpon' => $data['Nomor_Telp'],
                    'nomor_fax' => $data['Nomor_fax'],
                    'id_provinsi' => $data['Provinsi'],
                    'id_kabupaten_kota' => $data['Kabupaten_Kota'],
                    'id_kecamatan' => $data['Kecamatan'],
                    'id_kelurahan' => $data['Kelurahan'],
                    'tipe_kantor' => $data['Status_Gedung_Kantor'],
                    'jam_kerja_senin_kamis' => $data['JamKerjaSeninKamis'],
                    'jam_kerja_jumat' => $data['JamKerjaJumat'],
                    'jam_kerja_sabtu' => $data['JamKerjaSabtu'],
                    'frekuensi_antar_ke_alamat' => $data['FrekuensiAntarKeAlamat'],
                    'frekuensi_antar_ke_dari_kprk' => $data['FrekuensiKirimDariKeKprk'],
                    'jumlah_tenaga_kontrak' => $data['JumlahTenagaKontrak'],
                    'kondisi_gedung' => $data['KondisiGedung'],
                    'fasilitas_publik_dalam' => $data['FasilitasPublikDalamKantor'],
                    'fasilitas_publik_halaman' => $data['FasilitasPublikLuarKantor'],
                    'lingkungan_kantor' => $data['LingkunganKantor'],
                    'lingkungan_sekitar_kantor' => $data['LingkunganSekitarKantor'],
                    'tgl_sinkronisasi' => now(),
                    'tgl_update' => now(),
                    'jarak_ke_kprk' => $data['Jarak'],
                ];

                if ($existingKPC) {
		$existingKPC->update($kpcData);
                } else {
                    Kpc::create($kpcData);
                }

                $totalSumber++;
                $status = ($totalSumber == $totalTarget) ? 'success' : 'on progress';
                $updated_payload = $payload->payload ?? '';
                $jsonData = json_encode($data);
                $fileSize = strlen($jsonData);
                $data['size'] = $fileSize;
                if ($updated_payload !== '' || $payload->payload !== null) {
                    // Decode existing payload from JSON string to PHP array
                    $existing_payload = json_decode($updated_payload, true);
                    $existing_payload = is_array($existing_payload) ? $existing_payload : [$existing_payload];

                    // Convert new data to object if it's not already an object
                    $new_payload = (object) $data;

                    // Add new payload object to existing_payload array
                    $existing_payload[] = $new_payload;

                    // Encode updated payload array back to JSON
                    $updated_payload = json_encode($existing_payload);
                } else {
                    $updated_payload = json_encode([(object) $data]);
                }

                sleep(2);
                $payload->update([
                    'payload' => $updated_payload,
                ]);

                $apiRequestLog->update([
                    'successful_records' => $totalSumber,
                    'status' => $status,
                ]);

            }

        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
        }
    }
    public function maxAttempts()
    {
        return 5; // Set the maximum attempts
    }

    public function backoff()
    {
        return 10; // Set the delay in seconds before retrying the job
    }
    public function timeout()
{
    return 0; // No timeout for this job
}
}
