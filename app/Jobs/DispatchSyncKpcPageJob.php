<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Jobs\ProcessKpcChunkJob;
use Illuminate\Queue\SerializesModels;
use App\Http\Controllers\ApiController;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class DispatchSyncKpcPageJob implements ShouldQueue {
  public function handle() {
    $api = new ApiController();
    $resp = $api->makeRequest(HttpRequest::create('/', 'GET', [
      'end_point' => 'daftar_kpc', 'page' => $this->page, 'per_page' => $this->perPage
    ]));
    $nopends = collect($resp['data'] ?? [])->pluck('nopend')->filter()->values();

    $log = ApiRequestLog::create([
      'komponen' => 'KPC', 'tanggal' => now(),
      'ip_address' => gethostbyname(gethostname()),
      'platform_request' => $this->userAgent ?? 'unknown',
      'total_records' => $nopends->count(),
      'available_records' => $nopends->count(),
      'successful_records' => 0,
      'status' => 'Memproses Batch',
    ]);

    foreach ($nopends->chunk(100) as $chunk) {
      ProcessKpcChunkJob::dispatch($chunk->all(), $log->id);
    }
  }
}