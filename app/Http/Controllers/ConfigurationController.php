<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DataKey;

class ConfigurationController extends Controller
{
    public function getConfig() {
        return response()->json([
            'root_path' => public_path()
        ]);
    }

    public function saveDataKey() {
        DataKey::truncate();

        $response = [];
        $filename = storage_path('/app/configs/data_keys.csv');
        $file = fopen($filename, "r");
        $all_data = array();
        $idx = 0;
        while ( ($data = fgetcsv($file, 200, ",")) !==FALSE ) {
            if($idx > 0) {
                $data_key = new DataKey();
                $data_key->certificate_type = $data[2];
                $data_key->keyword = $data[0];
                $data_key->parameter = $data[1];
                $data_key->save();
                
                $response[] = $data_key;
            }
            
            $idx += 1;

        }
        fclose($file);

        return response()->json($response);
    }
}
