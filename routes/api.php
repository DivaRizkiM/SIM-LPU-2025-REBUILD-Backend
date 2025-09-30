<?php

use App\Models\KategoriPendapatan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\KpcController;
use App\Http\Controllers\LtkController;
use App\Http\Controllers\NppController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KprkController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ApiControllerV2;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\ApiLogController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PusherController;
use App\Http\Controllers\SyncApiController;
use App\Http\Controllers\UserLogController;
use App\Http\Controllers\ProvinsiController;
use App\Http\Controllers\RegionalController;
use App\Http\Controllers\BiayaGajiController;
use App\Http\Controllers\CodeCheckController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KecamatanController;
use App\Http\Controllers\KelurahanController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PencatatanController;
use App\Http\Controllers\PetugasKpcController;
use App\Http\Controllers\JenisBisnisController;
use App\Http\Controllers\JenisKantorController;
use App\Http\Controllers\ProfileBoLpuController;
use App\Http\Controllers\RekonsiliasiController;
use App\Http\Controllers\BiayaAtribusiController;
use App\Http\Controllers\KabupatenKotaController;
use App\Http\Controllers\KategoriBiayaController;
use App\Http\Controllers\PenyelenggaraController;
use App\Http\Controllers\RekeningBiayaController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\CakupanWilayahController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LockVerifikasiController;
use App\Http\Controllers\TargetAnggaranController;
use App\Http\Controllers\PerbaikanRinganController;
use App\Http\Controllers\RekeningProduksiController;
use App\Http\Controllers\VerifikasiLapanganController;
use App\Http\Controllers\VerifikasiProduksiController;
use App\Http\Controllers\LaporanDeviasiSoLpuController;
use App\Http\Controllers\BeritaAcaraPenarikanController;
use App\Http\Controllers\LaporanPrognosaBiayaController;
use App\Http\Controllers\VerifikasiBiayaRutinController;
use App\Http\Controllers\BeritaAcaraVerifikasiController;
use App\Http\Controllers\KertasKerjaVerifikasiController;
use App\Http\Controllers\LaporanRealisasiSoLpuController;
use App\Http\Controllers\LaporanVerifikasiBiayaController;
use App\Http\Controllers\MonitoringKantorUsulanController;
use App\Http\Controllers\MonitoringKantorExistingController;
use App\Http\Controllers\LaporanPrognosaPendapatanController;
use App\Http\Controllers\DashboardProduksiPendapatanController;
use App\Http\Controllers\LaporanVerifikasiPendapatanController;
use App\Http\Controllers\BeritaAcaraVerifikasiBulananController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
 */
// Route::get('/welcome', function () {
//     return view('welcome');
// })->name('welcome');
// Route::get('/clear-cache', function () {
//     $exitCode = Artisan::call('cache:clear');
//     return '<h1>Cache cleared</h1>';
// })->name('clear-cache');

// Route::get('/route-clear', function () {
//     $exitCode = Artisan::call('route:clear');
//     return '<h1>Route cache cleared</h1>';
// })->name('route-clear');

// Route::get('/config-cache', function () {
//     $exitCode = Artisan::call('config:cache');
//     return '<h1>Configuration cached</h1>';
// })->name('config-cache');

// Route::get('/optimize', function () {
//     $exitCode = Artisan::call('optimize');
//     return '<h1>Configuration cached</h1>';
// })->name('optimize');

// Route::get('/storage-link', function () {
//     $exitCode = Artisan::call('storage:link');
//     return '<h1>storage linked</h1>';
// })->name('optimize');

// Route::middleware(['csp'])->group(function () {
// Route::get('berita-acara-penarikan/pdf', [BeritaAcaraPenarikanController::class, 'pdf'])->name('berita-acara-penarikan');



Route::get('/profile-regional-test', [ApiControllerV2::class, 'getProfileRegional']);
Route::get('/get-token', [ApiController::class, 'getToken']);
Route::get('/get-signature', [ApiController::class, 'generateSignature']);
Route::get('/makeRequest', [ApiController::class, 'makeRequest']);
Route::get('/syncProvinsi', [ProvinsiController::class, 'syncProvinsi']);
Route::get('/syncKabupaten-kota', [KabupatenKotaController::class, 'syncKabupaten']);
Route::get('/syncKecamatan', [KecamatanController::class, 'syncKecamatan']);
Route::get('/syncKelurahan', [KelurahanController::class, 'syncKelurahan']);

Route::get('/syncRegional', [SyncApiController::class, 'syncRegional']);
Route::get('/syncKategoriBiaya', [SyncApiController::class, 'syncKategoriBiaya']);
Route::get('/syncRekeningBiaya', [SyncApiController::class, 'syncRekeningBiaya']);
Route::get('/syncKategoriPendapatan', [SyncApiController::class, 'syncKategoriPendapatan']);
Route::get('/syncPendapatan', [SyncApiController::class, 'syncPendapatan']);
Route::get('/syncLayananKurir', [SyncApiController::class, 'syncLayananKurir']);
Route::get('/syncLayananJasaKeuangan', [SyncApiController::class, 'syncLayananJasaKeuangan']);
Route::get('/syncRekeningProduksi', [SyncApiController::class, 'syncRekeningProduksi']);
Route::get('/syncTipeBisnis', [SyncApiController::class, 'syncTipeBisnis']);
Route::get('/syncPetugasKCP', [SyncApiController::class, 'syncPetugasKCP']);
Route::get('/syncKCU', [SyncApiController::class, 'syncKCU']);
Route::get('/syncLtk', [SyncApiController::class, 'syncMtdLtk']);
Route::get('/syncKPC', [SyncApiController::class, 'syncKPC']);
Route::get('/syncBiayaAtribusi', [SyncApiController::class, 'syncBiayaAtribusi']);
Route::get('/syncBiaya', [SyncApiController::class, 'syncBiaya']);
Route::get('/syncBiaya-prognosa', [SyncApiController::class, 'syncBiayaPrognosa']);
Route::get('/syncProduksi', [SyncApiController::class, 'syncProduksi']);
Route::get('/syncProduksi-prognosa', [SyncApiController::class, 'syncProduksiPrognosa']);
Route::get('/syncNpp', [SyncApiController::class, 'syncNpp']);
Route::get('/syncDashboardProduksiPendapatan', [SyncApiController::class, 'syncDashboardProduksiPendapatan']);
Route::get('/syncProduksi-nasional', [SyncApiController::class, 'syncProduksiNasional']);
Route::get('/syncLampiranBiaya', [SyncApiController::class, 'syncLampiran']);
Route::get('/api-log', [ApiLogController::class, 'index']);
Route::get('/api-log-detail/{id}', [ApiLogController::class, 'show']);
Route::get('/stop-sync', [ApiLogController::class, 'manageQueue']);

Route::controller(AuthController::class)->group(function () {
    // Route login tidak perlu middleware auth:api
    Route::post('/login', 'login')->name('login');
    Route::post('/register', 'register')->name('register');
    Route::get('/getProfile', 'getProfile')->name('getProfile');
    Route::get('/getUser/{id}', 'getUser')->name('getUser');
    Route::get('/getAlluser', 'getAlluser')->name('getAlluser');
    Route::delete('/deleteUser/{id}', 'deleteUser')->name('deleteUser');
    Route::post('/updateUser/{id}', 'updateUser')->name('updateUser');
    Route::post('/logout', 'logout')->name('logout');
    Route::get('/exportCSV', 'exportCSV')->name('exportCSV');
});

Route::controller(ForgotPasswordController::class)->group(function () {
    Route::get('/password/email', '__invoke')->name('postemail');
    Route::post('/password/email', '__invoke')->name('email');
});

Route::controller(CodeCheckController::class)->group(function () {
    Route::get('/password/code/check', '__invoke')->name('get_check');
    Route::post('/password/code/check', '__invoke')->name('post_check');
});

Route::controller(ResetPasswordController::class)->group(function () {
    Route::get('/password/reset', '__invoke')->name('postreset');
    Route::post('/password/reset', '__invoke')->name('reset');
});

Route::post('/reset-first-password', [ResetPasswordController::class, 'resetFirstPassword'])->name('reset-first-password');
// Route::post('/sendMessage/{eventName}', [PusherController::class, 'sendMessage'])->name('sendMessage');
// Route::get('/chatList', [PusherController::class, 'chatList']);
// Route::get('/chatList/{penerima_id}', [PusherController::class, 'chatDetail']);
// Route::get('/test', [PusherController::class, 'test'])->name('test');

Route::middleware('auth:api')->group(function () {
    Route::post('api-keys', [ApiKeyController::class, 'generateKey'])->name('api-keys.generate');
    Route::get('api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::delete('api-keys/{id}', [ApiKeyController::class, 'delete'])->name('api-keys.delete');
    Route::put('api-keys/deactivate/{id}', [ApiKeyController::class, 'deactivate'])->name('api-keys.deactivate');

    Route::middleware('api_key')->group(function () {
        Route::apiResource('user', UserController::class);
        Route::post('updatePassword', [UserController::class, 'updatePassword']);
        Route::get('user-verificator', [UserController::class, 'userVerificator']);
        Route::apiResource('lock-verifikasi', LockVerifikasiController::class);
        Route::post('lock-verifikasi-hapus/{id_lock}', [LockVerifikasiController::class, 'hapus']);
        Route::post('lock-verifikasi-edit/{id_lock}', [LockVerifikasiController::class, 'edit']);
        Route::apiResource('provinsi', ProvinsiController::class);
        Route::apiResource('kabupaten-kota', KabupatenKotaController::class);
        Route::apiResource('kecamatan', KecamatanController::class);
        Route::apiResource('kelurahan', KelurahanController::class);
        Route::apiResource('jenis-bisnis', JenisBisnisController::class);
        Route::apiResource('jenis-kantor', JenisKantorController::class);
        Route::apiResource('kprk', KprkController::class);
        Route::apiResource('kpc', KpcController::class);
        Route::apiResource('petugas-kpc', PetugasKpcController::class);
        Route::apiResource('penyelenggara', PenyelenggaraController::class);
        Route::apiResource('regional', RegionalController::class);
        Route::apiResource('kategori_pendapatan', KategoriPendapatan::class);
        Route::apiResource('rekening-biaya', RekeningBiayaController::class);
        Route::apiResource('rekening-produksi', RekeningProduksiController::class);
        Route::apiResource('kategori-biaya', KategoriBiayaController::class);
        Route::apiResource('target-anggaran', TargetAnggaranController::class);
        Route::get('target-anggaran-dashboard', [TargetAnggaranController::class, 'showDB']);

        Route::get('pencatatan', [PencatatanController::class, 'index']);
        Route::get('user-log', [UserLogController::class, 'index']);
        Route::get('pencatatan/{id}', [PencatatanController::class, 'show']);
        Route::post('pencatatan/save/{id?}', [PencatatanController::class, 'save']);
        Route::get('RealisasiBiaya-pie', [DashboardController::class, 'RealisasiBiaya']);
        Route::get('RealisasiPendapatan-donut', [DashboardController::class, 'RealisasiPendapatan']);
        Route::get('RealisasiBiaya-chart', [DashboardController::class, 'RealisasiBiayaChart']);
        Route::get('RealisasiAnggaran-gauge', [DashboardController::class, 'RealisasiAnggaran']);

        Route::post('rutin-download', [VerifikasiBiayaRutinController::class, 'downloadLampiran'])->name('rutin-download');


        Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring');
        Route::get('monitoring-detail', [MonitoringController::class, 'show'])->name('monitoring-detail');

        Route::apiResource('rekonsiliasi', RekonsiliasiController::class);
        Route::post('rekonsiliasi-import', [RekonsiliasiController::class, 'multistore'])->name('rekonsiliasi-import');

        Route::get('status', [UserController::class, 'status'])->name('status');
        Route::get('grup', [UserController::class, 'grup'])->name('grup');
        Route::get('kprk-regional', [KprkController::class, 'getByregional'])->name('kprk-regional');
        Route::get('kpc-regional', [KpcController::class, 'getByregional'])->name('kpc-regional');
        Route::get('kpc-kprk', [KpcController::class, 'getBykprk'])->name('kprk-kprk');
        Route::get('petugas-per-kpc', [PetugasKpcController::class, 'getBykpc'])->name('petugas-per-kpc');

        Route::get('atribusi-tahun', [BiayaAtribusiController::class, 'getPerTahun'])->name('atribusi-tahun');
        Route::get('atribusi-regional', [BiayaAtribusiController::class, 'getPerRegional'])->name('atribusi-regional');
        Route::get('atribusi-kcu', [BiayaAtribusiController::class, 'getPerKCU'])->name('atribusi-kcu');
        Route::get('atribusi-detail', [BiayaAtribusiController::class, 'getDetail'])->name('atribusi-detail');
        Route::post('atribusi-verifikasi', [BiayaAtribusiController::class, 'verifikasi'])->name('atribusi-verifikasi');

        Route::get('rutin-tahun', [VerifikasiBiayaRutinController::class, 'getPerTahun'])->name('rutin-tahun');
        Route::get('rutin-regional', [VerifikasiBiayaRutinController::class, 'getPerRegional'])->name('rutin-regional');
        Route::get('rutin-kcu', [VerifikasiBiayaRutinController::class, 'getPerKCU'])->name('rutin-kcu');
        Route::get('rutin-kpc', [VerifikasiBiayaRutinController::class, 'getPerKPC'])->name('rutin-kpc');
        Route::get('rutin-detail', [VerifikasiBiayaRutinController::class, 'getDetail'])->name('rutin-detail');
        Route::post('rutin-verifikasi', [VerifikasiBiayaRutinController::class, 'verifikasi'])->name('rutin-verifikasi');
        Route::post('rutin-verifikasi/all', [VerifikasiBiayaRutinController::class, 'submit'])->name('rutin-verifikasi-submit');
        Route::post('rutin-not-simpling', [VerifikasiBiayaRutinController::class, 'notSimpling'])->name('rutin-not-simpling');

        Route::get('produksi-tahun', [VerifikasiProduksiController::class, 'getPerTahun'])->name('produksi-tahun');
        Route::get('produksi-regional', [VerifikasiProduksiController::class, 'getPerRegional'])->name('produksi-regional');
        Route::get('produksi-kcu', [VerifikasiProduksiController::class, 'getPerKCU'])->name('produksi-kcu');
        Route::get('produksi-kpc', [VerifikasiProduksiController::class, 'getPerKPC'])->name('produksi-kpc');
        Route::get('produksi-detail', [VerifikasiProduksiController::class, 'getDetail'])->name('produksi-detail');
        Route::post('produksi-verifikasi', [VerifikasiProduksiController::class, 'verifikasi'])->name('produksi-verifikasi');
        Route::post('produksi-verifikasi/all', [VerifikasiProduksiController::class, 'submit'])->name('produksi-verifikasi-submit');
        Route::post('produksi-not-simpling', [VerifikasiProduksiController::class, 'notSimpling'])->name('produksi-not-simpling');

        Route::get('npp-tahun', [NppController::class, 'getPerTahun'])->name('npp-tahun');
        Route::get('npp-regional', [NppController::class, 'getPerRegional'])->name('npp-regional');
        Route::get('npp-kcu', [NppController::class, 'getPerKCU'])->name('npp-kcu');
        Route::get('npp-kpc', [NppController::class, 'getPerKPC'])->name('npp-kpc');
        Route::get('npp-detail', [NppController::class, 'getDetail'])->name('npp-detail');
        Route::post('npp-verifikasi', [NppController::class, 'verifikasi'])->name('npp-verifikasi');
        Route::post('npp-not-simpling', [NppController::class, 'notSimpling'])->name('npp-not-simpling');

        Route::get('ltk-tahun', [LtkController::class, 'getPerTahun'])->name('ltk-tahun');
        Route::get('ltk-detail', [LtkController::class, 'getDetail'])->name('ltk-detail');
        Route::post('ltk-verifikasi', [LtkController::class, 'verifikasi'])->name('ltk-verifikasi');

        Route::get('gaji-pegawai-tahun', [BiayaGajiController::class, 'getPerTahun'])->name('gaji-pegawai-tahun');
        Route::get('gaji-pegawai-detail', [BiayaGajiController::class, 'getDetail'])->name('gaji-pegawai-detail');
        Route::post('gaji-pegawai-verifikasi', [BiayaGajiController::class, 'verifikasi'])->name('gaji-pegawai-verifikasi');

        Route::get('dpp-tahun', [DashboardProduksiPendapatanController::class, 'getPerTahun'])->name('dpp-tahun');
        Route::get('dpp-regional', [DashboardProduksiPendapatanController::class, 'getPerRegional'])->name('dpp-regional');
        Route::get('dpp-kcu', [DashboardProduksiPendapatanController::class, 'getPerKCU'])->name('dpp-kcu');
        Route::get('dpp-kpc', [DashboardProduksiPendapatanController::class, 'getPerKPC'])->name('dpp-kpc');
        Route::get('dpp-detail', [DashboardProduksiPendapatanController::class, 'getDetail'])->name('dpp-detail');
        Route::post('dpp-verifikasi', [DashboardProduksiPendapatanController::class, 'verifikasi'])->name('dpp-verifikasi');
        Route::post('dpp-hapus/{id_dpp}', [DashboardProduksiPendapatanController::class, 'hapus'])->name('dpp-hapus');
        Route::post('dpp-not-simpling', [DashboardProduksiPendapatanController::class, 'notSimpling'])->name('dpp-not-simpling');

        Route::get('kertas-kerja-verifikasi', [KertasKerjaVerifikasiController::class, 'index'])->name('kertas-kerja-verifikasi');
        Route::get('kertas-kerja-verifikasi/detail', [KertasKerjaVerifikasiController::class, 'show'])->name('kertas-kerja-verifikasi-detail');
        Route::get('kertas-kerja-verifikasi/export', [KertasKerjaVerifikasiController::class, 'export'])->name('kertas-kerja-verifikasi-export');
        Route::get('kertas-kerja-verifikasi/export-detail', [KertasKerjaVerifikasiController::class, 'exportDetail'])->name('kertas-kerja-verifikasi-export-detai');

        Route::get('laporan-verifikasi-pendapatan', [LaporanVerifikasiPendapatanController::class, 'index'])->name('laporan-verifikasi-pendapatan');
        Route::get('laporan-verifikasi-pendapatan/export', [LaporanVerifikasiPendapatanController::class, 'export'])->name('laporan-verifikasi-pendapatan-export');

        Route::get('laporan-verifikasi-biaya', [LaporanVerifikasiBiayaController::class, 'index'])->name('laporan-verifikasi-biaya');
        Route::get('laporan-verifikasi-biaya/export', [LaporanVerifikasiBiayaController::class, 'export'])->name('laporan-verifikasi-biaya-export');

        Route::get('laporan-prognosa-biaya', [LaporanPrognosaBiayaController::class, 'index'])->name('laporan-prognosa-biaya');
        Route::get('laporan-prognosa-biaya/export', [LaporanPrognosaBiayaController::class, 'export'])->name('laporan-prognosa-biaya-export');

        Route::get('laporan-prognosa-pendapatan', [LaporanPrognosaPendapatanController::class, 'index'])->name('laporan-prognosa-pendapatan');
        Route::get('laporan-prognosa-pendapatan/export', [LaporanPrognosaPendapatanController::class, 'export'])->name('laporan-prognosa-pendapatan-export');

        Route::get('laporan-realisasi-so-lpu', [LaporanRealisasiSoLpuController::class, 'index'])->name('laporan-realisasi-so-lpu');
        Route::get('laporan-realisasi-so-lpu/export', [LaporanRealisasiSoLpuController::class, 'export'])->name('laporan-realisasi-so-lpu-export');

        Route::get('laporan-deviasi-so-lpu', [LaporanDeviasiSoLpuController::class, 'index'])->name('laporan-deviasi-so-lpu');
        Route::get('laporan-deviasi-so-lpu/export', [LaporanDeviasiSoLpuController::class, 'export'])->name('laporan-deviasi-so-lpu-export');

        Route::get('profile-bo-lpu', [ProfileBoLpuController::class, 'index'])->name('profile-bo-lpu');
        Route::get('profile-bo-lpu/export', [ProfileBoLpuController::class, 'export'])->name('profile-bo-lpu-export');

        Route::get('cakupan-wilayah', [CakupanWilayahController::class, 'index'])->name('cakupan-wilayah');
        Route::get('cakupan-wilayah/export', [CakupanWilayahController::class, 'export'])->name('cakupan-wilayah-export');

        Route::get('monitoring-kantor-existing', [MonitoringKantorExistingController::class, 'index'])->name('monitoring-kantor-existing');
        Route::get('monitoring-kantor-existing/export', [MonitoringKantorExistingController::class, 'export'])->name('monitoring-kantor-existing-export');

        Route::get('monitoring-kantor-usulan', [MonitoringKantorUsulanController::class, 'index'])->name('monitoring-kantor-usulan');
        Route::get('monitoring-kantor-usulan/export', [MonitoringKantorUsulanController::class, 'export'])->name('monitoring-kantor-usulan-export');

        Route::get('verifikasi-lapangan', [VerifikasiLapanganController::class, 'index'])->name('verifikasi-lapangan');
        Route::get('verifikasi-lapangan/export', [VerifikasiLapanganController::class, 'export'])->name('verifikasi-lapangan-export');

        Route::get('perbaikan-ringan', [PerbaikanRinganController::class, 'index'])->name('perbaikan-ringan');
        Route::get('perbaikan-ringan/export', [PerbaikanRinganController::class, 'export'])->name('perbaikan-ringan-export');
        Route::post('berita-acara-penarikan/pdf', [BeritaAcaraPenarikanController::class, 'pdf'])->name('berita-acara-penarikan');

        Route::post('berita-acara-verifikasi', [BeritaAcaraVerifikasiController::class, 'index'])->name('berita-acara-verifikasi');
        Route::post('berita-acara-verifikasi-bulanan', [BeritaAcaraVerifikasiBulananController::class, 'index'])->name('berita-acara-verifikasi-bulanan');

        Route::get('map-monitoring', [KpcController::class, 'map'])->name('map-monitoring');
        
        Route::get('/export-biaya', [ExportController::class, 'exportBiaya']);
        Route::get('/export-rekap-biaya', [ExportController::class, 'exportRekapBiaya']);
        Route::get('/export-pendapatan', [ExportController::class, 'exportPendapatan']);
        Route::get('/export-rekap-pendapatan', [ExportController::class, 'exportRekapPendapatan']);
    });
});
// });
