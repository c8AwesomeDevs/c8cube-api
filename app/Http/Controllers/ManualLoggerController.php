<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Auth;
use Validator;
use App\ManualLoggerBatch;
use App\ManualLoggerBatchDetails;

class ManualLoggerController extends Controller
{
    protected $key;
    protected $piwebapi;
    protected $piserver;

    public function __construct() {
        $this->key = env('JWT_KEY');
        $this->piwebapi = env('PIWEBAPI');
        $this->piserver = env('PISERVER');
    }

    public function isAuthenticated($token) {
        $user = JWT::decode($token, new Key($this->key, 'HS256'));
        return Auth::attempt(['email' => $user->email, 'password' => $user->password]);
    }

    public function getBatches() {
        $batches = ManualLoggerBatch::orderBy('id', 'DESC');
        return response()->json($batches->get());
    }

    public function saveBatch(Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $batch = new ManualLoggerBatch();
        $batch->uploaded_by = Auth::user()->id;
        $batch->name = $request->name;
        $batch->description = $request->description;
        $batch->uploaded_by = Auth::user()->id;
        $batch->save();

        return response()->json($batch);
    }

    public function getBatchDetails($id, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $batch = ManualLoggerBatch::find($id);
        return response()->json($batch);
    }

    public function saveBatchDetails($id, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $batch = ManualLoggerBatch::find($id);
        $batch->data = $request->getContent();
        $batch->validated = true;
        $batch->save();

        return response()->json($batch);
    }

    public function saveAndWrite($id, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $batch = ManualLoggerBatch::find($id);
        $batch->data = $request->getContent();
        $batch->validated = true;
        $batch->written = true;
        $batch->save();

        $data = json_decode($batch->data, true);
        $columns = [];
        foreach($data['columns'] as $idx => $c) {
            if ($idx > 0){
                $columns[$idx] = [
                    'name' => $c,
                    'values' => []
                ];
            }
        }

        foreach($data['rows'] as $idx => $row) {
            foreach($row['values'] as $v_idx => $v) {
                if($v_idx == 0) {
                    $timestamp = $v;
                }
                else {
                    $columns[$v_idx]['values'][] = [
                        'Timestamp' => date('Y-m-d\TH:i:s\Z', strtotime($timestamp)),
                        'Value' => $v
                    ];
                }
            }
        }

        $response = [];
        foreach($columns as $idx => $c) {
            $batch_request = [
                1 => [
                    'Resource' => $this->piwebapi . '/points?selectedFields=Links.RecordedData&path=\\\\' . $this->piserver . '\\' . $c['name'],
                    'Method' => 'GET'
                ],
                2 => [
                    'Resource' => '$.1.Content.Links.RecordedData',
                    'Method' => 'POST',
                    'Content' => json_encode($c['values']),
                    'ParentIds' => ['1']
                ]
            ];

            $batch_url = $this->piwebapi . '/batch';
            $piwebapi = new PIWebAPIController();

            $response[] = $piwebapi->postRequest($batch_url, $batch_request);
        }

        return response()->json($response, 200);
    }

    public function uploadBatchDetails($id, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = $file->getClientOriginalExtension();
            if($ext != 'csv') {
                return response()->json([
                    'message' => 'Invalid File Format'
                ], 400);
            }

            $batch = ManualLoggerBatch::find($id);
            $filename = $batch->name . '.' . $ext;


            $file->move(public_path('/uploads/manual-logger/' . $batch->id), $filename);

            $rows = [];
            $columns = [];
            $row = 1;
            if (($handle = fopen(public_path('/uploads/manual-logger/' . $batch->id) . '/' . $filename, "r")) !== FALSE) {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if($row == 1) {
                        $columns = $data;
                    }
                    else {
                        if($data[0]) {
                            $data[0] = date('Y-m-d H:i:s', strtotime($data[0]));
                            $rows[]['values'] = $data;
                        }
                    }

                    $row += 1;
                }
                fclose($handle);
            }

            $ml_data = [
                'columns' => $columns,
                'rows' => $rows
            ];

            $batch->data = json_encode($ml_data);
            $batch->save();

            return response()->json($batch);
        }
        $batch = ManualLoggerBatch::find($id);
        
        return response()->json($batch);
    }
}
