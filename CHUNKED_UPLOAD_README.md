# API Upload Foto - Direct vs Chunked

## 📋 Problem

Server FrankenPHP memiliki limit `max_file_uploads = 20`. Saat user upload 25 foto sekaligus, server error:
```
frankenphp_handle_request(): Maximum number of allowable file uploads has been exceeded
```

## ✅ Solusi: 2 Cara Upload

### 1️⃣ Direct Upload (Method Lama - Tetap Bisa Dipakai)
**Untuk ≤ 20 foto** → Upload langsung sekaligus ke `/api/pencatatan/save`

### 2️⃣ Chunked Upload (Method Baru)
**Untuk > 20 foto** → Upload bertahap 5 foto per batch ke `/api/pencatatan/upload-chunked`, lalu finalize

---

## 🔀 Kapan Pakai Yang Mana?

```
Jumlah foto yang akan diupload:
    ↓
≤ 20 foto?
    │
    ├─ YES → Pakai Direct Upload (/api/pencatatan/save)
    │         ✅ Simple, 1 request aja
    │         ✅ Lebih cepat
    │
    └─ NO (> 20 foto) → Pakai Chunked Upload
              ✅ Upload bertahap per 5 foto
              ✅ Progress tracking
              ✅ Bisa handle sampai 100+ foto
```

---

## � Perbandingan Direct vs Chunked

| Aspek | Direct Upload | Chunked Upload |
|-------|---------------|----------------|
| **Endpoint** | `POST /api/pencatatan/save` | `POST /api/pencatatan/upload-chunked` + `POST /api/pencatatan/finalize-chunked` |
| **Jumlah Request** | 1 request | 6+ requests (5 batch + 1 finalize) untuk 25 foto |
| **Max Foto** | 20 foto | Unlimited (tested sampai 100+) |
| **Kompleksitas** | Simple | Medium |
| **Progress Tracking** | ❌ | ✅ (per batch) |
| **Retry Logic** | ❌ (gagal harus ulang semua) | ✅ (retry per batch) |
| **Kapan Pakai** | ≤ 20 foto | > 20 foto |

---

## 🔄 Flow Upload

### Flow 1: Direct Upload (≤ 20 Foto)

```text
User pilih 15 foto
    ↓
POST /api/pencatatan/save
  - files[0-14]: binary files
  - pencatatan_kantor: {...}
  - pencatatan_kantor_kuis: [...]
    ↓
✅ Selesai (1 request)
```

### Flow 2: Chunked Upload (> 20 Foto)

```text
User pilih 25 foto
    ↓
Split jadi 5 batch @ 5 foto
    ↓
Batch 1: Upload foto 1-5   → POST /api/pencatatan/upload-chunked
Batch 2: Upload foto 6-10  → POST /api/pencatatan/upload-chunked
Batch 3: Upload foto 11-15 → POST /api/pencatatan/upload-chunked
Batch 4: Upload foto 16-20 → POST /api/pencatatan/upload-chunked
Batch 5: Upload foto 21-25 → POST /api/pencatatan/upload-chunked
    ↓
Finalize dengan data lengkap → POST /api/pencatatan/finalize-chunked
    ↓
✅ Selesai (6 requests)
```

---

## 💡 Contoh Penggunaan

### Contoh 1: Direct Upload (15 Foto)

**Request:**

```http
POST /api/pencatatan/save
Content-Type: multipart/form-data
Authorization: Bearer YOUR_TOKEN

file[0]: (binary foto1.jpg)
file[1]: (binary foto2.jpg)
...
file[14]: (binary foto15.jpg)
pencatatan_kantor: {
  "id_kpc": "KPC001",
  "id_user": 123,
  "id_provinsi": 31,
  "latitude": -6.200000,
  "longitude": 106.816666,
  "tanggal": "2026-02-03"
}
pencatatan_kantor_user: [{"id_user": 123}]
pencatatan_kantor_kuis: [
  {
    "id_tanya": 1,
    "id_jawab": 1,
    "data": "Jawaban 1",
    "file": {"nama": "Foto Depan"}
  },
  ... (15 items total)
]
```

**Response:**

```json
{
  "status": "SUCCESS",
  "message": "Pencatatan berhasil disimpan",
  "data": {
    "id": 12345
  }
}
```

**Implementasi Android:**

```kotlin
// Direct upload - simple, 1 request aja
fun uploadDirect(photos: List<File>, data: PencatatanData) {
    val requestBody = MultipartBody.Builder()
        .setType(MultipartBody.FORM)
        
    // Attach photos
    photos.forEachIndexed { index, photo ->
        val fileBody = photo.asRequestBody("image/jpeg".toMediaType())
        requestBody.addFormDataPart("file[$index]", photo.name, fileBody)
    }
    
    // Attach data
    requestBody.addFormDataPart("pencatatan_kantor", data.pencatatanKantor.toJson())
    requestBody.addFormDataPart("pencatatan_kantor_user", data.users.toJsonArray())
    requestBody.addFormDataPart("pencatatan_kantor_kuis", data.kuis.toJsonArray())
    
    val response = apiService.pencatatanSave(requestBody.build())
    if (response.isSuccess) {
        showSuccess("Upload berhasil!")
    }
}
```

---

### Contoh 2: Chunked Upload (25 Foto)

**Step 1-5: Upload Batch (Hanya Foto)**

```http
POST /api/pencatatan/upload-chunked
Content-Type: multipart/form-data
Authorization: Bearer YOUR_TOKEN

session_id: "abc-123-def-456"
batch_number: 1
total_batches: 5
files[0]: (binary foto1.jpg)
files[1]: (binary foto2.jpg)
files[2]: (binary foto3.jpg)
files[3]: (binary foto4.jpg)
files[4]: (binary foto5.jpg)
```

**Response:**

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

**Step 6: Finalize (Kirim Semua Data)**

```http
POST /api/pencatatan/finalize-chunked
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN

{
  "session_id": "abc-123-def-456",
  "pencatatan_kantor": {
    "id_kpc": "KPC001",
    "id_user": 123,
    "id_provinsi": 31,
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
    ... (25 items total, sesuai urutan foto yang diupload)
  ]
}
```

**Response:**

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

**Implementasi Android:**

```kotlin
fun uploadChunked(photos: List<File>, data: PencatatanData) {
    val sessionId = UUID.randomUUID().toString()
    val batches = photos.chunked(5)
    val totalBatches = batches.size
    
    // Step 1-5: Upload foto per batch
    batches.forEachIndexed { index, batch ->
        val batchNumber = index + 1
        
        val result = uploadBatch(
            sessionId = sessionId,
            batchNumber = batchNumber,
            totalBatches = totalBatches,
            files = batch
        )
        
        if (result.isError) {
            showError("Gagal upload batch $batchNumber")
            return
        }
        
        // Update progress UI
        val progress = (batchNumber * 100) / totalBatches
        updateProgress(progress)
    }
    
    // Step 6: Finalize dengan semua data
    val finalResult = finalizeUpload(sessionId, data)
    if (finalResult.isSuccess) {
        showSuccess("Upload 25 foto berhasil!")
    }
}

fun uploadBatch(
    sessionId: String,
    batchNumber: Int,
    totalBatches: Int,
    files: List<File>
): ApiResponse {
    val requestBody = MultipartBody.Builder()
        .setType(MultipartBody.FORM)
        .addFormDataPart("session_id", sessionId)
        .addFormDataPart("batch_number", batchNumber.toString())
        .addFormDataPart("total_batches", totalBatches.toString())
    
    files.forEachIndexed { index, file ->
        val fileBody = file.asRequestBody("image/jpeg".toMediaType())
        requestBody.addFormDataPart("files[$index]", file.name, fileBody)
    }
    
    return apiService.uploadChunked(requestBody.build())
}

fun finalizeUpload(sessionId: String, data: PencatatanData): ApiResponse {
    val payload = JSONObject().apply {
        put("session_id", sessionId)
        put("pencatatan_kantor", data.pencatatanKantor.toJson())
        put("pencatatan_kantor_user", data.users.toJsonArray())
        put("pencatatan_kantor_kuis", data.kuis.toJsonArray())
    }
    
    return apiService.finalizeChunked(payload)
}
```

---

## 🛠️ API Reference

### API 1: Direct Upload

**Endpoint:** `POST /api/pencatatan/save`

**Digunakan untuk:** Upload ≤ 20 foto langsung

**Content-Type:** `multipart/form-data`

**Parameters:**

| Field | Type | Required | Deskripsi |
| ----- | ---- | -------- | --------- |
| `file` | array of files | Yes | Array file foto (format: file[0], file[1], ..., max 20) |
| `pencatatan_kantor` | object/string | Yes | Data pencatatan kantor (bisa JSON string atau object) |
| `pencatatan_kantor_user` | array/string | Optional | Array user (bisa JSON string atau array) |
| `pencatatan_kantor_kuis` | array/string | Yes | Array kuis (bisa JSON string atau array) |

---

### API 2: Chunked Upload

#### 2.1 Upload Batch

**Endpoint:** `POST /api/pencatatan/upload-chunked`

**Content-Type:** `multipart/form-data`

**Parameters:**

| Field | Type | Required | Deskripsi |
|-------|------|----------|-----------|
| `session_id` | string (UUID) | No | ID tracking untuk batch. Jika kosong, akan auto-generate. **Gunakan session_id yang SAMA untuk semua batch!** |
| `batch_number` | integer | Yes | Nomor batch (1, 2, 3, ...) |
| `total_batches` | integer | Yes | Total batch yang akan diupload |
| `files` | array of files | Yes | Array file foto (max 5 per batch) |

**Request Example:**

```http
POST /api/pencatatan/upload-chunked
Content-Type: multipart/form-data

session_id: "abc-123-def"
batch_number: 1
total_batches: 5
files[0]: (binary foto_1.jpg)
files[1]: (binary foto_2.jpg)
files[2]: (binary foto_3.jpg)
files[3]: (binary foto_4.jpg)
files[4]: (binary foto_5.jpg)
```

**Response Success:**

```json
{
  "status": "SUCCESS",
  "message": "Batch berhasil diupload",
  "data": {
    "session_id": "abc-123-def",
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

### 2. Finalize Chunked

**Endpoint:** `POST /api/pencatatan/finalize-chunked`

**Content-Type:** `application/json`

**Call setelah SEMUA batch berhasil diupload**

**Parameters:**

| Field | Type | Required | Deskripsi |
|-------|------|----------|-----------|
| `session_id` | string | Yes | Session ID dari upload-chunked |
| `pencatatan_kantor` | object | Yes | Data pencatatan kantor |
| `pencatatan_kantor_user` | array | Optional | Array user |
| `pencatatan_kantor_kuis` | array | Yes | Array kuis (urutan harus sesuai urutan foto) |

**Request Example:**

```json
{
  "session_id": "abc-123-def",
  "pencatatan_kantor": {
    "id_kpc": "KPC001",
    "id_user": 123,
    "id_provinsi": 31,
    "latitude": -6.200000,
    "longitude": 106.816666,
    "tanggal": "2026-02-03"
  },
  "pencatatan_kantor_user": [
    { "id_user": 123 }
  ],
  "pencatatan_kantor_kuis": [
    {
      "id_tanya": 1,
      "id_jawab": 1,
      "data": "Jawaban 1",
      "file": { "nama": "Foto Depan" }
    },
    {
      "id_tanya": 2,
      "id_jawab": 2,
      "data": "Jawaban 2",
      "file": { "nama": "Foto Belakang" }
    }
    // ... 25 items total
  ]
}
```

**Response Success:**

```json
{
  "status": "SUCCESS",
  "message": "Pencatatan berhasil disimpan",
  "data": {
    "id": 12345,
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

---

## 💻 Implementasi Android (Pseudocode)

```kotlin
fun uploadVerifikasiLapangan(
    pencatatanData: PencatatanKantor,
    selectedPhotos: List<File>
) {
    // 1. Generate session ID
    val sessionId = UUID.randomUUID().toString()
    
    // 2. Split foto jadi batch @ 5 foto
    val batches = selectedPhotos.chunked(5)
    val totalBatches = batches.size
    
    // 3. Upload setiap batch
    batches.forEachIndexed { index, batch ->
        val batchNumber = index + 1
        
        val result = uploadChunkedBatch(
            sessionId = sessionId,
            batchNumber = batchNumber,
            totalBatches = totalBatches,
            files = batch
        )
        
        if (result.isError) {
            showError("Gagal upload batch $batchNumber")
            return
        }
        
        updateProgress(batchNumber, totalBatches)
    }
    
    // 4. Finalize
    val finalResult = finalizeChunkedUpload(
        sessionId = sessionId,
        pencatatanData = pencatatanData
    )
    
    if (finalResult.isSuccess) {
        showSuccess("Upload berhasil!")
    }
}

fun uploadChunkedBatch(
    sessionId: String,
    batchNumber: Int,
    totalBatches: Int,
    files: List<File>
): ApiResponse {
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

fun finalizeChunkedUpload(
    sessionId: String,
    pencatatanData: PencatatanKantor
): ApiResponse {
    val payload = JSONObject().apply {
        put("session_id", sessionId)
        put("pencatatan_kantor", pencatatanData.toJson())
        put("pencatatan_kantor_user", pencatatanData.users.toJsonArray())
        put("pencatatan_kantor_kuis", pencatatanData.kuis.toJsonArray())
    }
    
    return apiService.finalizeChunked(payload)
}
```

---

## ⚠️ Catatan Penting

1. **Session ID sama**: Gunakan session_id yang SAMA untuk semua batch. Jangan generate baru tiap batch.

2. **Batch Number**: Mulai dari 1, bukan 0.

3. **Max 5 foto per batch**: Lebih dari 5 akan error.

4. **Session expiry**: Cache 1 jam. Jika lewat 1 jam belum finalize, harus upload ulang.

5. **Index mapping**: 
   - Batch 1 → index 0-4
   - Batch 2 → index 5-9
   - Batch 3 → index 10-14
   - dst...

6. **Urutan kuis**: Array `pencatatan_kantor_kuis` harus sesuai urutan foto. Index 0 kuis = index 0 foto.

---

## 🧪 Testing dengan cURL

### Upload Batch 1:

```bash
curl -X POST "https://verifikasilpu.komdigi.go.id/backend/api/pencatatan/upload-chunked" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "session_id=test-123" \
  -F "batch_number=1" \
  -F "total_batches=2" \
  -F "files[0]=@foto1.jpg" \
  -F "files[1]=@foto2.jpg" \
  -F "files[2]=@foto3.jpg" \
  -F "files[3]=@foto4.jpg" \
  -F "files[4]=@foto5.jpg"
```

### Upload Batch 2:

```bash
curl -X POST "https://verifikasilpu.komdigi.go.id/backend/api/pencatatan/upload-chunked" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "session_id=test-123" \
  -F "batch_number=2" \
  -F "total_batches=2" \
  -F "files[0]=@foto6.jpg" \
  -F "files[1]=@foto7.jpg" \
  -F "files[2]=@foto8.jpg" \
  -F "files[3]=@foto9.jpg" \
  -F "files[4]=@foto10.jpg"
```

### Finalize:

```bash
curl -X POST "https://verifikasilpu.komdigi.go.id/backend/api/pencatatan/finalize-chunked" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "test-123",
    "pencatatan_kantor": {...},
    "pencatatan_kantor_kuis": [...]
  }'
```

---

## 📞 Support

Error? Cek log di server:

```bash
tail -f /var/www/backend/storage/logs/laravel.log
```

Prefix error: `Upload Chunked Error` atau `Finalize Chunked Error`
