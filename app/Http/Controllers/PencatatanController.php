<?php

namespace App\Http\Controllers;

use App\Models\PencatatanKantor;
use App\Models\PencatatanKantorUser;
use App\Models\PencatatanKantorKuis;
use App\Models\PencatatanKantorFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
class PencatatanController extends Controller
{

    public function index(Request $request)
    {
        try {
            // Mengambil data dengan join tabel terkait dan paginasi
            $data = DB::table('pencatatan_kantor')
                ->leftJoin('pencatatan_kantor_user', 'pencatatan_kantor.id', '=', 'pencatatan_kantor_user.id_parent')
                ->leftJoin('pencatatan_kantor_kuis', 'pencatatan_kantor.id', '=', 'pencatatan_kantor_kuis.id_parent')
                ->leftJoin('pencatatan_kantor_file', 'pencatatan_kantor.id', '=', 'pencatatan_kantor_file.id_parent')
                ->select('pencatatan_kantor.*', 'pencatatan_kantor_user.id_user', 'pencatatan_kantor_kuis.id_tanya', 'pencatatan_kantor_file.file_name')
                ->get();
                $total_data = $data->count();

                return response()->json([
                    'status' => 'SUCCESS',
                    'total_data'=>$total_data,
                    'data' => $data,
                ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error fetching data: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve data.'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            // Mengambil data berdasarkan ID dengan join tabel terkait
            $model = DB::table('pencatatan_kantor')
                ->leftJoin('pencatatan_kantor_user', 'pencatatan_kantor.id', '=', 'pencatatan_kantor_user.id_parent')
                ->leftJoin('pencatatan_kantor_kuis', 'pencatatan_kantor.id', '=', 'pencatatan_kantor_kuis.id_parent')
                ->leftJoin('pencatatan_kantor_file', 'pencatatan_kantor.id', '=', 'pencatatan_kantor_file.id_parent')
                ->select('pencatatan_kantor.*', 'pencatatan_kantor_user.id_user', 'pencatatan_kantor_kuis.id_tanya', 'pencatatan_kantor_file.file_name')
                ->where('pencatatan_kantor.id', $id)
                ->first();

            if (!$model) {
                throw new ModelNotFoundException('Data not found.');
            }

            return response()->json($model, 200);
        } catch (ModelNotFoundException $e) {
            // Log the error for debugging
            dd($e);
            \Log::error('Model not found: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Data not found.'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            dd($e);
            // Log the error for debugging
            \Log::error('Error fetching data: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve data.'
            ], 500);
        }
    }

    // public function save(Request $request, $id = null)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $model = $id ? PencatatanKantor::findOrFail($id) : new PencatatanKantor;

    //         $model->fill($request->all());
    //         if (!$model->save()) {
    //             return response()->json($model->errors, Response::HTTP_BAD_REQUEST);
    //         }

    //         // Handle users
    //         foreach ($request->input('user', []) as $user) {
    //             $user['id_parent'] = $model->id;
    //             $user['id_user'] = $user['id_user'] ?? auth()->id();
    //             $tmpUser = PencatatanKantorUser::updateOrCreate([
    //                 'id_parent' => $user['id_parent'],
    //                 'id_user' => $user['id_user']
    //             ], $user);

    //             if (!$tmpUser) {
    //                 DB::rollBack();
    //                 return response()->json($tmpUser->errors, Response::HTTP_BAD_REQUEST);
    //             }
    //         }

    //         // Handle kuis
    //         foreach ($request->input('kuis', []) as $kuis) {
    //             $kuis['id_parent'] = $model->id;
    //             $tmpKuis = PencatatanKantorKuis::updateOrCreate([
    //                 'id_parent' => $kuis['id_parent'],
    //                 'id_tanya' => $kuis['id_tanya'],
    //                 'id_jawab' => $kuis['id_jawab'],
    //             ], $kuis);

    //             if (!$tmpKuis) {
    //                 DB::rollBack();
    //                 return response()->json($tmpKuis->errors, Response::HTTP_BAD_REQUEST);
    //             }
    //         }

    //         // Handle files
    //         foreach ($request->input('file', []) as $file) {
    //             $file['id_parent'] = $model->id;
    //             $tmpFile = PencatatanKantorFile::updateOrCreate([
    //                 'id_parent' => $file['id_parent'],
    //                 'nama' => $file['nama']
    //             ], $file);

    //             if ($request->hasFile("file.{$tmpFile->id}")) {
    //                 $uploadedFile = $request->file("file.{$tmpFile->id}");
    //                 $path = $uploadedFile->store('files');
    //                 $tmpFile->file = Storage::get($path);
    //                 $tmpFile->save();
    //             }

    //             if (!$tmpFile) {
    //                 DB::rollBack();
    //                 return response()->json($tmpFile->errors, Response::HTTP_BAD_REQUEST);
    //             }
    //         }

    //         DB::commit();
    //         return response()->json(['status' => 'success', 'data' => $model], 200);
    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }
    // }
    public function save(Request $request, $id = null)
    {
        DB::beginTransaction();
        try {
            $model = $id ? PencatatanKantor::findOrFail($id) : new PencatatanKantor;

            $model->fill($request->all());
            if (!$model->save()) {
                return response()->json($model->errors, Response::HTTP_BAD_REQUEST);
            }
            if ($request->input('user', [])) {
                foreach ($request->input('user', []) as $user) {
                    $user['id_parent'] = $model->id;
                    $user['id_user'] = $user['id_user'] ?? auth()->id();

                    // Update or create the user record
                    $tmpUser = PencatatanKantorUser::updateOrCreate([
                        'id_parent' => $user['id_parent'],
                        'id_user' => $user['id_user']
                    ], $user);

                    // Error handling is not needed here as updateOrCreate always returns a model
                }
            } else {
                // If no user data is provided, create a default user record
                PencatatanKantorUser::updateOrCreate([
                    'id_parent' => $model->id,
                    'id_user' => auth()->id(), // Set the authenticated user's ID
                ]);
            }

                        // Handle users


            // Handle kuis
            foreach ($request->input('kuis', []) as $kuis) {
                $kuis['id_parent'] = $model->id;
                $tmpKuis = PencatatanKantorKuis::updateOrCreate([
                    'id_parent' => $kuis['id_parent'],
                    'id_tanya' => $kuis['id_tanya'],
                    'id_jawab' => $kuis['id_jawab'],
                ], $kuis);

                if (!$tmpKuis) {
                    DB::rollBack();
                    return response()->json($tmpKuis->errors, Response::HTTP_BAD_REQUEST);
                }
            }

            // Set the destination path
            $destinationPath = storage_path('app/public/pencatatan');

            // Create directory if it doesn't exist
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

                // Handle files
                foreach ($request->file('file', []) as $key => $uploadedFile) {
                    if ($uploadedFile) {
                        // Access the corresponding name
                        $fileName = $request->input("file.{$key}.nama");

                        // Generate a unique file name
                        $uniqueFileName = time() . '_' . $uploadedFile->getClientOriginalName();

                        // Move the uploaded file
                        $uploadedFile->move($destinationPath, $uniqueFileName);

                        // Create a new file record
                        $tmpFile = new PencatatanKantorFile([
                            'id_parent' => $model->id,
                            'nama' => $fileName, // Use the name from the input
                            'file' => $uniqueFileName,
                            'file_name' => $uploadedFile->getClientOriginalName(),
                            'file_type' => $uploadedFile->getClientMimeType(),
                            'created' => now(),
                            'updated' => now(),
                        ]);

                        $tmpFile->save();
                    }
                }


            DB::commit();
            return response()->json(['status' => 'success', 'data' => $model], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }




}
