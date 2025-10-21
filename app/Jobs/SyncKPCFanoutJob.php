<?php

namespace App\Jobs;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use App\Http\Controllers\ApiController;

class SyncKPCFanoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $endpoint,
        protected string $endpointProfile,
        protected string $userAgent
    ) {}

    public function handle()
    {
        $api = new ApiController();

        // 1) ambil daftar KPC sekali saja
        $req = request()->duplicate(); // jangan pakai request() yang sama-sana, clone aman
        $req->merge(['end_point' => $this->endpoint]);
        $resp = $api->makeRequest($req);

        $list = $resp['data'] ?? [];
        if (empty($list)) return;

        // siapkan log payung
        $apiRequestLog = ApiRequestLog::create([
            'komponen' => 'KPC',
            'tanggal' => now(),
            'ip_address' => gethostbyname(gethostname()),
            'platform_request' => (new \Jenssegers\Agent\Agent())->setUserAgent($this->userAgent)->platform().'/'.(new \Jenssegers\Agent\Agent())->browser(),
            'successful_records' => 0,
            'available_records' => $resp['total_data'] ?? count($list),
            'total_records' => 0,
            'status' => 'Memuat Data',
        ]);

        // 2) pecah per chunk (50–100 bagus)
        $chunks = array_chunk($list, 50);

        $jobs = [];
        foreach ($chunks as $chunk) {
            // passing hanya data minimal yang dibutuhkan (mis. nopend)
            $jobs[] = new FetchKPCProfilesChunkJob(
                array_map(fn($x) => $x['nopend'] ?? null, $chunk),
                $this->endpointProfile,
                $this->userAgent,
                $apiRequestLog->id
            );
        }

        // 3) buat batch (fan-out)
        Bus::batch($jobs)
            ->name('Sync KPC Batch')
            ->allowFailures()
            ->then(function (Batch $batch) use ($apiRequestLog) {
                $apiRequestLog->update(['status' => 'success']);
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($apiRequestLog) {
                $apiRequestLog->update(['status' => 'failed']);
            })
            ->finally(function (Batch $batch) use ($apiRequestLog) {
                // finalize
            })
            ->dispatch();
    }
}
