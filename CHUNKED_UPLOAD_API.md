# 📸 API Upload Foto - Chunked Method

## 🎯 Ringkasan

**SEMUA upload foto sekarang WAJIB pakai chunked method!** Method `store()` sudah tidak dipakai lagi.

- **Upload berapa foto aja** → Pakai chunked (5 foto per batch)
- **5 foto** → 1 batch → Pakai chunked
- **18 foto** → 4 batch → Pakai chunked  
- **25 foto** → 5 batch → Pakai chunked
- **100 foto** → 20 batch → Pakai chunked

---

## ❌ Method Lama TIDAK DIPAKAI

**Endpoint `/api/pencatatan/save` (method `store()`) SUDAH DEPRECATED!**

- ❌ Jangan pakai lagi untuk development baru
- ❌ Endpoint tetap ada cuma untuk backward compatibility aplikasi lama
- ✅ **Semua upload baru WAJIB pakai chunked method**

---

## 📋 Kenapa Chunked untuk Semua?

1. ✅ **No limit** - FrankenPHP limit 20 files, chunked bisa unlimited
2. ✅ **Progress tracking** - User lihat progress tiap batch
3. ✅ **Retry per batch** - Gagal? Retry batch itu aja, gak perlu ulang semua
4. ✅ **Better UX** - Progress indicator untuk user experience
5. ✅ **Konsisten** - 1 cara untuk semua kasus, gak perlu if-else
6. ✅ **Tested** - Sudah tested sampai 100+ foto

---

## 🔄 Flow Upload

```text
User pilih X foto
    ↓
Split jadi batch @ 5 foto per batch
    ↓
Loop: Upload tiap batch ke /api/pencatatan/upload-chunked
  - Update progress UI setiap batch selesai
    ↓
Finalize: POST /api/pencatatan/finalize-chunked
  - Kirim semua data pencatatan
    ↓
✅ Done
```

### Contoh:
- **5 foto** → 1 batch → 2 request total (1 upload + 1 finalize)
- **13 foto** → 3 batch → 4 request total (3 upload + 1 finalize)
- **25 foto** → 5 batch → 6 request total (5 upload + 1 finalize)

---

## 🛠️ API Endpoints

### Endpoint 1: Upload Batch

```http
POST /api/pencatatan/upload-chunked
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

**Parameters:**

| Field | Type | Required | Deskripsi |
|-------|------|----------|-----------|
| `session_id` | string (UUID) | No | ID session untuk tracking. Jika kosong auto-generate. **GUNAKAN SESSION_ID YANG SAMA untuk semua batch!** |
| `batch_number` | integer | Yes | Nomor batch (1, 2, 3, ...) |
| `total_batches` | integer | Yes | Total batch yang akan diupload |
| `files` | array | Yes | Max 5 foto per batch. Format: `files[0]`, `files[1]`, ..., `files[4]` |

**Request Example:**

```bash
curl -X POST "https://verifikasilpu.komdigi.go.id/backend/api/pencatatan/upload-chunked" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "session_id=abc-123-def-456" \
  -F "batch_number=1" \
  -F "total_batches=5" \
  -F "files[0]=@foto1.jpg" \
  -F "files[1]=@foto2.jpg" \
  -F "files[2]=@foto3.jpg" \
  -F "files[3]=@foto4.jpg" \
  -F "files[4]=@foto5.jpg"
```

**Response Success:**

```json
{
  "status": "SUCCESS",
  "message": "Batch berhasil diupload",
  "data": {
    "session_id": "abc-123-def-456",
    "batch_number": 1,
    "total_batches": 5,
    "uploaded_count": 5,
    "total_files": 5,
    "is_complete": false
  }
}
```

**Response Error:**

```json
{
  "status": "ERROR",
  "message": "Maksimal 5 foto per batch"
}
```

---

### Endpoint 2: Finalize Upload

```http
POST /api/pencatatan/finalize-chunked
Content-Type: application/json
Authorization: Bearer {token}
```

**Parameters:**

| Field | Type | Required | Deskripsi |
|-------|------|----------|-----------|
| `session_id` | string | Yes | Session ID dari upload-chunked (HARUS SAMA) |
| `pencatatan_kantor` | object | Yes | Data pencatatan kantor |
| `pencatatan_kantor_user` | array | Optional | Array user yang terlibat |
| `pencatatan_kantor_kuis` | array | Yes | Array kuis (urutan sesuai urutan foto) |

**Request Example:**

```json
{
  "session_id": "abc-123-def-456",
  "pencatatan_kantor": {
    "id_kpc": "KPC001",
    "id_user": 123,
    "id_provinsi": 31,
    "id_kabupaten": 3171,
    "id_kecamatan": 3171010,
    "id_kelurahan": 3171010001,
    "jenis": "verifikasi",
    "latitude": -6.200000,
    "longitude": 106.816666,
    "tanggal": "2026-02-03"
  },
  "pencatatan_kantor_user": [
    {"id_user": 123}
  ],
  "pencatatan_kantor_kuis": [
    {
      "id_tanya": 1,
      "id_jawab": 1,
      "data": "Jawaban 1",
      "file": {"nama": "Foto Depan"}
    },
    {
      "id_tanya": 2,
      "id_jawab": 2,
      "data": "Jawaban 2",
      "file": {"nama": "Foto Belakang"}
    }
    // ... total sesuai jumlah foto
  ]
}
```

**Response Success:**

```json
{
  "status": "SUCCESS",
  "message": "Pencatatan berhasil disimpan",
  "data": {
    "id": 12346,
    "total_files": 25
  }
}
```

**Response Error:**

```json
{
  "status": "ERROR",
  "message": "Session tidak ditemukan atau sudah expired"
}
```

atau

```json
{
  "status": "ERROR",
  "message": "Masih ada batch yang belum diupload. Received: 3/5"
}
```

---

## 💻 Implementasi Android (Kotlin)

### Full Code

```kotlin
class UploadService {
    
    /**
     * Upload pencatatan dengan foto menggunakan chunked method
     * @param photos List foto yang akan diupload
     * @param data Data pencatatan lengkap
     */
    suspend fun uploadPencatatan(
        photos: List<File>,
        data: PencatatanData
    ): Result<FinalizeResponse> = withContext(Dispatchers.IO) {
        try {
            // 1. Generate session ID
            val sessionId = UUID.randomUUID().toString()
            
            // 2. Split foto jadi batch @ 5 foto
            val batches = photos.chunked(5)
            val totalBatches = batches.size
            
            // 3. Upload setiap batch
            batches.forEachIndexed { index, batch ->
                val batchNumber = index + 1
                
                // Update progress
                val progress = ((batchNumber - 1) * 100) / totalBatches
                updateProgress(progress, "Mengupload batch $batchNumber/$totalBatches...")
                
                // Upload batch
                val result = uploadBatch(
                    sessionId = sessionId,
                    batchNumber = batchNumber,
                    totalBatches = totalBatches,
                    files = batch
                )
                
                if (!result.isSuccess) {
                    // Retry logic
                    val retry = retryBatch(sessionId, batchNumber, totalBatches, batch)
                    if (!retry.isSuccess) {
                        return@withContext Result.failure(
                            Exception("Gagal upload batch $batchNumber: ${result.message}")
                        )
                    }
                }
            }
            
            // 4. Finalize
            updateProgress(100, "Menyimpan data...")
            val finalResult = finalizeUpload(sessionId, data)
            
            if (finalResult.isSuccess) {
                Result.success(finalResult.data)
            } else {
                Result.failure(Exception("Gagal finalize: ${finalResult.message}"))
            }
            
        } catch (e: Exception) {
            Log.e("UploadService", "Error upload: ${e.message}", e)
            Result.failure(e)
        }
    }
    
    /**
     * Upload 1 batch (max 5 foto)
     */
    private suspend fun uploadBatch(
        sessionId: String,
        batchNumber: Int,
        totalBatches: Int,
        files: List<File>
    ): ApiResult<UploadChunkedResponse> {
        val requestBody = MultipartBody.Builder()
            .setType(MultipartBody.FORM)
            .addFormDataPart("session_id", sessionId)
            .addFormDataPart("batch_number", batchNumber.toString())
            .addFormDataPart("total_batches", totalBatches.toString())
        
        files.forEachIndexed { index, file ->
            val fileBody = file.asRequestBody("image/jpeg".toMediaType())
            requestBody.addFormDataPart(
                "files[$index]",
                file.name,
                fileBody
            )
        }
        
        return apiService.uploadChunked(requestBody.build())
    }
    
    /**
     * Retry batch jika gagal
     */
    private suspend fun retryBatch(
        sessionId: String,
        batchNumber: Int,
        totalBatches: Int,
        files: List<File>,
        maxRetries: Int = 3
    ): ApiResult<UploadChunkedResponse> {
        repeat(maxRetries) { attempt ->
            Log.d("UploadService", "Retry batch $batchNumber, attempt ${attempt + 1}")
            delay(1000 * (attempt + 1)) // Backoff: 1s, 2s, 3s
            
            val result = uploadBatch(sessionId, batchNumber, totalBatches, files)
            if (result.isSuccess) {
                return result
            }
        }
        
        return ApiResult.failure("Gagal setelah $maxRetries kali retry")
    }
    
    /**
     * Finalize upload dengan data lengkap
     */
    private suspend fun finalizeUpload(
        sessionId: String,
        data: PencatatanData
    ): ApiResult<FinalizeResponse> {
        val payload = mapOf(
            "session_id" to sessionId,
            "pencatatan_kantor" to data.pencatatanKantor,
            "pencatatan_kantor_user" to data.users,
            "pencatatan_kantor_kuis" to data.kuis
        )
        
        return apiService.finalizeChunked(payload)
    }
    
    /**
     * Update progress UI
     */
    private fun updateProgress(progress: Int, message: String) {
        _uploadProgress.value = UploadProgress(progress, message)
    }
}
```

### Retrofit Interface

```kotlin
interface ApiService {
    
    @Multipart
    @POST("api/pencatatan/upload-chunked")
    suspend fun uploadChunked(
        @Part body: MultipartBody
    ): ApiResult<UploadChunkedResponse>
    
    @POST("api/pencatatan/finalize-chunked")
    suspend fun finalizeChunked(
        @Body payload: Map<String, Any>
    ): ApiResult<FinalizeResponse>
}
```

### Data Classes

```kotlin
data class UploadChunkedResponse(
    val status: String,
    val message: String,
    val data: UploadChunkedData
)

data class UploadChunkedData(
    val session_id: String,
    val batch_number: Int,
    val total_batches: Int,
    val uploaded_count: Int,
    val total_files: Int,
    val is_complete: Boolean
)

data class FinalizeResponse(
    val status: String,
    val message: String,
    val data: FinalizeData
)

data class FinalizeData(
    val id: Int,
    val total_files: Int
)

data class UploadProgress(
    val progress: Int,
    val message: String
)

data class PencatatanData(
    val pencatatanKantor: PencatatanKantor,
    val users: List<PencatatanUser>,
    val kuis: List<PencatatanKuis>
)
```

### ViewModel Example

```kotlin
class PencatatanViewModel : ViewModel() {
    
    private val uploadService = UploadService()
    
    private val _uploadProgress = MutableLiveData<UploadProgress>()
    val uploadProgress: LiveData<UploadProgress> = _uploadProgress
    
    private val _uploadResult = MutableLiveData<Result<FinalizeResponse>>()
    val uploadResult: LiveData<Result<FinalizeResponse>> = _uploadResult
    
    fun submitPencatatan(photos: List<File>, data: PencatatanData) {
        viewModelScope.launch {
            val result = uploadService.uploadPencatatan(photos, data)
            _uploadResult.value = result
        }
    }
}
```

### UI Example (Fragment)

```kotlin
class PencatatanFragment : Fragment() {
    
    private val viewModel: PencatatanViewModel by viewModels()
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        // Observe progress
        viewModel.uploadProgress.observe(viewLifecycleOwner) { progress ->
            binding.progressBar.progress = progress.progress
            binding.tvProgress.text = progress.message
        }
        
        // Observe result
        viewModel.uploadResult.observe(viewLifecycleOwner) { result ->
            result.onSuccess { response ->
                Toast.makeText(
                    context,
                    "Upload ${response.data.total_files} foto berhasil!",
                    Toast.LENGTH_SHORT
                ).show()
                // Navigate atau update UI
            }
            result.onFailure { error ->
                Toast.makeText(
                    context,
                    "Upload gagal: ${error.message}",
                    Toast.LENGTH_LONG
                ).show()
            }
        }
        
        // Submit button
        binding.btnSubmit.setOnClickListener {
            val photos = getSelectedPhotos() // List<File>
            val data = buildPencatatanData() // PencatatanData
            
            viewModel.submitPencatatan(photos, data)
        }
    }
}
```

---

## ⚠️ Catatan Penting

### 1. Session ID

**WAJIB pakai session_id yang SAMA untuk semua batch!**

❌ **SALAH:**
```kotlin
batches.forEach { batch ->
    val sessionId = UUID.randomUUID() // JANGAN GINI!
    uploadBatch(sessionId, ...)
}
```

✅ **BENAR:**
```kotlin
val sessionId = UUID.randomUUID() // Generate 1x aja
batches.forEach { batch ->
    uploadBatch(sessionId, ...) // Pakai yang sama
}
```

### 2. Batch Number

- Mulai dari **1**, bukan 0
- Batch pertama = 1, kedua = 2, dst

### 3. Max Files per Batch

- **Max 5 foto per batch**
- Lebih dari 5 akan error
- Gunakan `.chunked(5)` untuk auto-split

### 4. Session Expiry

- Cache session 1 jam
- Lewat 1 jam belum finalize → data hilang
- Harus upload ulang

### 5. File Index Mapping

Backend auto-mapping berdasarkan batch:
- Batch 1: files[0-4] → index global 0-4
- Batch 2: files[0-4] → index global 5-9  
- Batch 3: files[0-4] → index global 10-14
- dst...

### 6. Urutan Kuis

`pencatatan_kantor_kuis` array **HARUS** sesuai urutan foto:
- Index 0 kuis = index 0 foto
- Index 1 kuis = index 1 foto
- dst...

### 7. Progress UI

Update progress setiap batch selesai:
```kotlin
val progress = (batchNumber - 1) * 100 / totalBatches
updateProgress(progress, "Uploading $batchNumber/$totalBatches")
```

### 8. Retry Logic

Jika 1 batch gagal:
- ✅ Retry batch yang gagal aja
- ❌ JANGAN ulang semua batch

### 9. Error Handling

Handle error per batch:
```kotlin
if (!result.isSuccess) {
    // Option 1: Retry
    retryBatch(...)
    
    // Option 2: Cancel & show error
    showError("Batch $batchNumber gagal")
    return
}
```

---

## 🧪 Testing

### Test 1: Upload 5 Foto (1 Batch)

```bash
# Batch 1
curl -X POST "https://verifikasilpu.komdigi.go.id/backend/api/pencatatan/upload-chunked" \
  -H "Authorization: Bearer TOKEN" \
  -F "session_id=test-123" \
  -F "batch_number=1" \
  -F "total_batches=1" \
  -F "files[0]=@foto1.jpg" \
  -F "files[1]=@foto2.jpg" \
  -F "files[2]=@foto3.jpg" \
  -F "files[3]=@foto4.jpg" \
  -F "files[4]=@foto5.jpg"

# Finalize
curl -X POST "https://verifikasilpu.komdigi.go.id/backend/api/pencatatan/finalize-chunked" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "test-123",
    "pencatatan_kantor": {...},
    "pencatatan_kantor_kuis": [...]
  }'
```

### Test 2: Upload 25 Foto (5 Batch)

```bash
# Batch 1-5
for i in {1..5}; do
  curl -X POST "https://verifikasilpu.komdigi.go.id/backend/api/pencatatan/upload-chunked" \
    -H "Authorization: Bearer TOKEN" \
    -F "session_id=test-456" \
    -F "batch_number=$i" \
    -F "total_batches=5" \
    -F "files[0]=@foto$((($i-1)*5+1)).jpg" \
    -F "files[1]=@foto$((($i-1)*5+2)).jpg" \
    -F "files[2]=@foto$((($i-1)*5+3)).jpg" \
    -F "files[3]=@foto$((($i-1)*5+4)).jpg" \
    -F "files[4]=@foto$((($i-1)*5+5)).jpg"
done

# Finalize
curl -X POST "https://verifikasilpu.komdigi.go.id/backend/api/pencatatan/finalize-chunked" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "test-456",
    "pencatatan_kantor": {...},
    "pencatatan_kantor_kuis": [... 25 items ...]
  }'
```

---

## 🐛 Troubleshooting

### Error: "Maksimal 5 foto per batch"

**Penyebab:** Kirim lebih dari 5 foto dalam 1 batch

**Solusi:**
```kotlin
// SALAH
val batch = photos // 10 foto

// BENAR
val batches = photos.chunked(5) // Split jadi 2 batch @ 5 foto
```

---

### Error: "Session tidak ditemukan"

**Penyebab:** 
- Session ID salah
- Sudah lewat 1 jam (expired)
- Session ID berbeda tiap batch

**Solusi:**
```kotlin
// Generate session ID 1x aja
val sessionId = UUID.randomUUID().toString()

// Pakai yang sama untuk SEMUA batch
batches.forEach { batch ->
    uploadBatch(sessionId, ...) // session_id SAMA
}

// Finalize juga pakai yang sama
finalizeUpload(sessionId, ...) // session_id SAMA
```

---

### Error: "Masih ada batch yang belum diupload"

**Penyebab:**
- Ada batch yang terlewat (misal: upload batch 1, 2, 4 → batch 3 hilang)
- Ada batch yang gagal tapi tidak retry

**Solusi:**
```kotlin
// Pastikan semua batch success sebelum finalize
batches.forEachIndexed { index, batch ->
    val result = uploadBatch(...)
    if (!result.isSuccess) {
        // WAJIB retry atau cancel
        throw Exception("Batch ${index + 1} gagal")
    }
}
```

---

### Upload Lambat?

**Ini normal** karena upload bertahap (batch by batch)

**Benefit:**
- ✅ Progress tracking
- ✅ Retry per batch (gak perlu ulang semua)
- ✅ User bisa lihat progress
- ✅ Better error handling

**Tips:**
- Show progress bar dengan % dan message
- Upload di background thread
- Implement retry logic dengan backoff

---

## 📞 Support

### Check Logs di Server

```bash
tail -f /var/www/backend/storage/logs/laravel.log | grep "VERLAP"
```

**Log Prefix:**
- `VERLAP FINALIZE CHUNKED Error` - Error saat finalize
- `Upload Chunked Error` - Error saat upload batch

### Contact

Kalau masih error, kasih info:
- Session ID yang dipakai
- Total foto yang diupload
- Batch mana yang error
- Error message lengkap
- Log dari Android (Logcat)

---

## 📚 Summary

| Item | Value |
|------|-------|
| **Endpoint Upload** | `POST /api/pencatatan/upload-chunked` |
| **Endpoint Finalize** | `POST /api/pencatatan/finalize-chunked` |
| **Max per Batch** | 5 foto |
| **Session Expiry** | 1 jam |
| **Method Lama** | `store()` - DEPRECATED |
| **Dokumentasi Backend** | Laravel Controller: `VerifikasiLapanganController` |
| **Routes** | `routes/api.php` line 346-347 |

**Prinsip Utama:**
1. ✅ **Semua upload pakai chunked** - Gak ada pilihan lain
2. ✅ **5 foto per batch** - Jangan lebih
3. ✅ **Session ID sama** - 1 session untuk semua batch
4. ✅ **Upload → Finalize** - 2 step process
5. ✅ **Progress tracking** - Update UI setiap batch
6. ✅ **Retry per batch** - Gagal? Retry batch itu aja
