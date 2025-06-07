<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DashboardProduksiPendapatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DashboardProduksiPendapatanController extends Controller
{
        public function getPerTahun(Request $request)
        {
            try {
                $validator = Validator::make($request->all(), [
                    'tanggal' => 'nullable|numeric',
                ]);
                if ($validator->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $validator->errors(),
                        'error_code' => 'INPUT_VALIDATION_ERROR',
                    ], 422);
                }

                $offset = $request->get('offset', 0);
                $limit = $request->get('limit', 100);
                $search = $request->get('search', '');
                $getOrder = $request->get('order', '');
                $tanggal = $request->get('tanggal', '');
                $status = $request->get('status', '');
                $groupProduk = $request->get('group_produk', '');
                $bisnis = $request->get('bisnis', '');
                $defaultOrder = $getOrder ? $getOrder : "tanggal ASC";
                $orderMappings = [
                    'tanggalASC' => 'dashboard_produksi_pendapatan.tanggal ASC',
                    'tanggalDESC' => 'dashboard_produksi_pendapatan.tanggal DESC',
                    'group_produkASC' => 'dashboard_produksi_pendapatan.group_produk ASC',
                    'group_produkDESC' => 'dashboard_produksi_pendapatan.group_produk DESC',
                    'bisnisASC' => 'dashboard_produksi_pendapatan.bisnis ASC',
                    'bisnisDESC' => 'dashboard_produksi_pendapatan.bisnis DESC',
                    'statusASC' => 'dashboard_produksi_pendapatan.status ASC',
                    'statusDESC' => 'dashboard_produksi_pendapatan.status DESC',
                ];

                $order = $orderMappings[$getOrder] ?? $defaultOrder;
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

                $dppQuery = DashboardProduksiPendapatan::orderByRaw($order)
                    ->select('id', 'group_produk', 'bisnis', 'status', 'tanggal', 'jml_produksi', 'jml_pendapatan', 'koefisien', 'transfer_pricing');

                if ($tanggal !== '') {
                    $dppQuery->where('tanggal', 'LIKE', '%' . $tanggal . '%');
                }
                if ($status !== '') {
                    $dppQuery->where('status','LIKE', '%' . $tanggal . '%');
                }
                if ($groupProduk !== '') {
                    $dppQuery->where('group_produk', 'LIKE', '%' . $groupProduk . '%');
                }
                if ($bisnis !== '') {
                    $dppQuery->where('bisnis', 'LIKE', '%' . $bisnis . '%');
                }

                $total_data = $dppQuery->count();
                $dpp = $dppQuery->offset($offset)
                    ->limit($limit)
                    ->get();

                $grand_total = $dpp->sum('jml_pendapatan');
                $grand_total = "Rp " . number_format(round($grand_total), 0, '', '.');

                // Menambahkan informasi status dan lock
                // foreach ($dpp as $item) {
                //     // $statusItem = Status::find($item->status);
                //     // $item->status_label = $statusItem ? $statusItem->nama : 'Unknown';

                //     $isLock = LockVerifikasi::where('tahun', date('Y', strtotime($item->tanggal)))
                //         ->where('bulan', date('m', strtotime($item->tanggal)))
                //         ->first();
                //     $item->isLock = $isLock ? $isLock->status : false;
                // }

                return response()->json([
                    'status' => 'SUCCESS',
                    'offset' => $offset,
                    'limit' => $limit,
                    'order' => $getOrder,
                    'search' => $search,
                    'total_data' => $total_data,
                    'grand_total' => $grand_total,
                    'data' => $dpp,
                ]);
            } catch (\Exception $e) {
                return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
            }
        }

    public function getDetail($id)
    {
        $data = DashboardProduksiPendapatan::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data fetched successfully',
            'data' => $data,
        ], 200);
    }

    public function verifikasi(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status_verifikasi' => 'required|in:approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = DashboardProduksiPendapatan::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found',
            ], 404);
        }

        $data->status_verifikasi = $request->status_verifikasi;
        $data->save();

        return response()->json([
            'success' => true,
            'message' => 'Verification status updated successfully',
            'data' => $data,
        ], 200);
    }
}
