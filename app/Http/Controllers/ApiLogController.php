<?php

namespace App\Http\Controllers;

use App\Models\ApiRequestLog;
use App\Models\ApiRequestPayloadLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class ApiLogController extends Controller
{
    public function index()
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $defaultOrder = $getOrder ? $getOrder : "tanggal DESC";
            $orderMappings = [
                'idASC' => 'id ASC',
                'idDESC' => 'id DESC',
                'komponenASC' => 'komponen ASC',
                'komponenDESC' => 'komponen DESC',
            ];

            // Set the order based on the mapping or use the default order if not found
            $order = $orderMappings[$getOrder] ?? $defaultOrder;
            // Validation rules for input parameters
            $validOrderValues = implode(',', array_keys($orderMappings));
            $rules = [
                'offset' => 'integer|min:0',
                'limit' => 'integer|min:1',
                'order' => "in:$validOrderValues",
            ];

            $validator = Validator::make([
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
            ], $rules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Invalid input parameters',
                    'errors' => $validator->errors(),
                ], 400);
            }
            $apiLogsQuery = ApiRequestLog::orderByRaw($order);
            $total_Data = $apiLogsQuery->count();

            if ($search !== '') {
                $apiLogsQuery->where('komponen', 'like', "%$search%");
            }

            $apiLogs = $apiLogsQuery->offset($offset)
                ->limit($limit)->get()
                ->map(function ($apiLog) {
                    $proses = $apiLog->successful_records . '/' . $apiLog->available_records;
                    if ($apiLog->available_records != 0) {
                        $persentase = number_format(($apiLog->successful_records / $apiLog->available_records) * 100, 2) . '%';
                    } else {
                        $persentase = '0%';
                    }

                    $arr = $apiLog->toArray();
                    $arr['proses'] = $proses;
                    $arr['persentase'] = $persentase;

                    // Format updated_at/tanggal ke timezone aplikasi sebagai string
                    if (!empty($apiLog->updated_at)) {
                        $arr['updated_at'] = $apiLog->updated_at
                            ->setTimezone(config('app.timezone'))
                            ->toDateTimeString();
                    } else {
                        $arr['updated_at'] = null;
                    }

                    if (!empty($apiLog->tanggal)) {
                        // jika ada kolom tanggal juga ingin ditampilkan di timezone app
                        try {
                            $arr['tanggal'] = \Carbon\Carbon::parse($apiLog->tanggal)
                                ->setTimezone(config('app.timezone'))
                                ->toDateTimeString();
                        } catch (\Exception $e) {
                            // biarkan nilai aslinya bila parse gagal
                            $arr['tanggal'] = $apiLog->tanggal;
                        }
                    }

                    return $arr;
                });
            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_Data,
                'data' => $apiLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $apiLog = ApiRequestPayloadLog::select(
                    'api_request_payload_logs.id',
                    'api_request_payload_logs.payload',
                    'api_request_logs.komponen',
                    'api_request_logs.tanggal',
                    'api_request_logs.ip_address',
                    'api_request_logs.platform_request',
                    'api_request_logs.total_records',
                    'api_request_logs.successful_records',
                    'api_request_logs.available_records',
                    'api_request_logs.status'
                )
                ->join('api_request_logs', 'api_request_payload_logs.api_request_log_id', '=', 'api_request_logs.id')
                ->where('api_request_logs.id', $id)
                ->first();

            if (!$apiLog) {
                return response()->json(['status' => 'ERROR', 'message' => 'Log not found'], 404);
            }

            // Calculate process and percentage
            $apiLog->proses = $apiLog->successful_records . '/' . $apiLog->available_records;

            if ($apiLog->available_records != 0) { // Ensure no division by zero
                $persentase = number_format(($apiLog->successful_records / $apiLog->available_records) * 100, 2) . '%';
                $apiLog->persentase = $persentase;
            } else {
                $apiLog->persentase = '0%';
            }
            $payload = json_decode($apiLog->payload);

            // Check if payload is an array and format size for each item
            if (is_array($payload)) {
                foreach ($payload as $item) {
                    if (isset($item->size)) {
                        $item->size = $this->formatFileSize($item->size);
                    }
                }
            } elseif (isset($payload->size)) { // If payload is a single object
                $payload->size = $this->formatFileSize($payload->size);
            }

            // Prepare the response data structure
            $response = [
                'status' => 'SUCCESS',
                'log_info' => [ // Other attributes outside data
                    'id' => $apiLog->id,
                    'komponen' => $apiLog->komponen,
                    'tanggal' => $apiLog->tanggal,
                    'ip_address' => $apiLog->ip_address,
                    'platform_request' => $apiLog->platform_request,
                    'total_records' => $apiLog->total_records,
                    'successful_records' => $apiLog->successful_records,
                    'available_records' => $apiLog->available_records,
                    'proses' => $apiLog->proses,
                    'persentase' => $apiLog->persentase,
                ],
                'data' =>  $payload,
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
    private function formatFileSize($size)
{
    if ($size >= 1073741824) { // 1 GB
        return number_format($size / 1073741824, 2) . ' GB';
    } elseif ($size >= 1048576) { // 1 MB
        return number_format($size / 1048576, 2) . ' MB';
    } elseif ($size >= 1024) { // 1 KB
        return number_format($size / 1024, 2) . ' KB';
    } else { // bytes
        return $size . ' bytes';
    }
}

public function manageQueue()
{
    // Restart the queue worker
    Artisan::call('queue:restart');

    // Clear the queue
    Artisan::call('queue:clear');

    // Flush the queue
    Artisan::call('queue:flush');
    Artisan::call('optimize:clear');
    Artisan::call('cache:clear');

    // // Restart Supervisor processes
    // $this->restartSupervisor();

    // Update the status of records
    $this->updateStatus();

    return response()->json(['message' => 'Queue managed successfully.']);
}

protected function updateStatus()
{
    // Update statuses in ApiRequestLog model
    ApiRequestLog::whereIn('status', ['Memuat Data', 'On Progress'])
        ->update(['status' => 'Dibatalkan']);
}

protected function restartSupervisor()
{
    $password = 'simlpu'; // Gantilah dengan kata sandi Anda
    $user = 'S1m!lpu#24'; // Gantilah dengan nama pengguna yang sesuai

    // Array untuk menyimpan output dan status setiap perintah
    $commands = [
        "echo $password | su - $user -c 'supervisorctl stop all'",
        "echo $password | su - $user -c 'systemctl restart supervisor'",
        "echo $password | su - $user -c 'supervisorctl start all'"
    ];

    foreach ($commands as $command) {
        exec($command, $output, $return_var);

        // Log output untuk debugging
        \Log::info('Command output: ' . implode("\n", $output));

        if ($return_var !== 0) {
            \Log::error('Command failed: ' . $command . ' - Output: ' . implode("\n", $output));
        }
    }
}



}
