<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\TagConfiguration;
use App\TagConfigurationFile;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DB;
use Auth;

class TagConfigurationController extends Controller
{
    protected $key;

    public function __construct() {
        $this->key = env('JWT_KEY');
    }
    public function isAuthenticated($token) {
        $user = JWT::decode($token, new Key($this->key, 'HS256'));

        return Auth::attempt(['email' => $user->email, 'password' => $user->password]);
    }

    public function saveConfig() {
        $path = public_path('/uploads/Tag Configurations/1/config.csv');
        $rows = array_map('str_getcsv', file($path));

        $response = [];
        foreach($rows as $idx => $row) {
            if($idx > 0) {
                $tagConfiguration = new TagConfiguration();
                $tagConfiguration->parameter = str_replace(' ', '-', strtolower(trim($row[0])));
                $tagConfiguration->tagname = $row[1];
                $tagConfiguration->datatype = $row[2];
                $tagConfiguration->document_type = 'coal';
                $tagConfiguration->save();

                $newTag = $this->saveTag($tagConfiguration->tagname, $tagConfiguration->datatype, $tagConfiguration->document_type);
                
                $response[] = $newTag;
            }
        }

        return response()->json($response);
    }

    public function uploadTagConfigurations($type, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }
        if(Auth::user()->access_level != 'admin') {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        ini_set('max_execution_time', 600);
        if($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = $type . '-' . date('Y-m-d_H-i-s') . "." . $file->getClientOriginalExtension();
            $file->move(public_path('/uploads/tag-configurations'), $filename);

            DB::beginTransaction();
            try {
                $tagConfigurationFile = new TagConfigurationFile();
                $tagConfigurationFile->filename = $filename;
                $tagConfigurationFile->uploaded_by = 1;
                $tagConfigurationFile->save();

                $oldTagConfigurations = TagConfiguration::where('document_type', $type)->delete();

                $rows = array_map('str_getcsv', file(public_path('/uploads/tag-configurations/' . $filename)));
                foreach($rows as $idx => $row) {
                    if($idx > 0) {
                        $tagConfiguration = new TagConfiguration();
                        $tagConfiguration->parameter = str_replace(' ', '-', strtolower(trim($row[0])));;
                        $tagConfiguration->tagname = $row[1];
                        $tagConfiguration->datatype = $row[2];
                        $tagConfiguration->document_type = $type;
                        $tagConfiguration->save();

                        $newTag = $this->saveTag($tagConfiguration->tagname, $tagConfiguration->datatype, $tagConfiguration->document_type);
                    }
                }
                DB::commit();

                saveLog(Auth::user()->id, sprintf('Successfully updated %s tag configuration', strtoupper($type)));

                $tagConfigurations = TagConfiguration::where('document_type', $type);
                return response()->json($tagConfigurations->get(), 201);
                // all good
            } catch (\Exception $e) {
                DB::rollback();
                saveLog(Auth::user()->id, sprintf('Failed to update %s tag configuration', strtoupper($type)));
                return response()->json($e, 500);
            }
        }

        return response()->json(['message' => 'File not found.'], 403);
    }
    
    public function saveTag($tagname,$dataType, $pointsource) {
        $piwebapi = new PIWebAPIController();
        $tag_details = $piwebapi->getTagDetails($tagname);
        $tag = null;

        if($tag_details['code'] == 404) {
            $tag = $piwebapi->createTag($tagname, $dataType, $pointsource);
        }
        else {
            $tag = $tag_details;
        }

        return $tag;
    }

    public function getTagConfigurations($type, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        $configurations = TagConfiguration::where('document_type', $type);

        return response()->json($configurations->get());
    }
}
