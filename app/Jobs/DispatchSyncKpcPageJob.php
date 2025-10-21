<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Models\ApiRequestLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchSyncKpcPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // param yang dikirim dari controller
    public function __construct(
        public int $page,
        public int $perPage = 1000,
        public ?string $userAgent = null
    ) {}

    public function handle()
    {
        $api = new ApiController();

        $resp = $api->makeRequest(HttpRequest::create('/', 'GET', [
            'end_point' => 'daftar_kpc',
            'page'      => $this->page,
            'per_page'  => $this->perPage,
        ]));

        $nopends = collect($resp['data'] ?? [])
            ->pluck('nopend')
            ->filter()
            ->values();

        // satu log untuk batch halaman ini
        $log = ApiRequestLog::create([
            'komponen'           => 'KPC',
            'tanggal'            => now(),
            'ip_address'         => gethostbyname(gethostname()),
            'platform_request'   => $this->userAgent ?? 'unknown',
            'total_records'      => $nopends->count(),
            'available_records'  => $nopends->count(),
            'successful_records' => 0,
            'status'             => 'Memproses Batch',
        ]);

        // pecah menjadi sub-job per 100 nopend
        foreach ($nopends->chunk(100) as $chunk) {
            ProcessKpcChunkJob::dispatch($chunk->all(), $log->id, $this->userAgent);
        }
    }
}
