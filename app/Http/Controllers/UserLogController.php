<?php

namespace App\Http\Controllers;

use App\Models\UserLog;
use App\Models\ApiRequestPayloadLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class UserLogController extends Controller
{
    public function index()
    {
        try {
            $offset = request()->get('offset', 0);
            $limit = request()->get('limit', 10);
            $search = request()->get('search', '');
            $getOrder = request()->get('order', '');
            $defaultOrder = $getOrder ? $getOrder : "timestamp DESC";
            $orderMappings = [
                'idASC' => 'id ASC',
                'idDESC' => 'id DESC',
                'timestampASC' => 'timestamp ASC',
                'timestampDESC' => 'timestamp DESC',
                'aktifitasASC' => 'aktifitas ASC',
                'aktifitasDESC' => 'aktifitas DESC',
                'modulASC' => 'modul ASC',
                'modulDESC' => 'modul DESC',
                'id_userASC' => 'id_user ASC',
                'id_userDESC' => 'id_user DESC',
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

          // Inisialisasi query dengan join ke tabel user
$userLogQuery = UserLog::join('user', 'user_log.id_user', '=', 'user.id')
->select('user_log.*', 'user.nama as nama_user')
->orderByRaw($order);

$total_data = $userLogQuery->count();

if ($search !== '') {
$userLogQuery->where(function($query) use ($search) {
    $query->where('user_log.id', 'like', "%$search%")
          ->orWhere('user_log.timestamp', 'like', "%$search%")
          ->orWhere('user_log.aktifitas', 'like', "%$search%")
          ->orWhere('user_log.modul', 'like', "%$search%")
          ->orWhere('user.name', 'like', "%$search%"); // Tambahkan pencarian berdasarkan name
});
}

$userLog = $userLogQuery->offset($offset)
->limit($limit)
->get();

            return response()->json([
                'status' => 'SUCCESS',
                'offset' => $offset,
                'limit' => $limit,
                'order' => $getOrder,
                'search' => $search,
                'total_data' => $total_data,
                'data' => $userLog,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }


}
