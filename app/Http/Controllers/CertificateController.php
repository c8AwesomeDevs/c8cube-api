<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Certificate;
use App\TagConfiguration;
use App\CertificateParameter;
use App\DataKey;
use DateTime;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Auth;
use DB;

class CertificateController extends Controller
{
    protected $key;

    public function __construct() {
        $this->key = env('JWT_KEY');
    }
    public function isAuthenticated($token) {
        $user = JWT::decode($token, new Key($this->key, 'HS256'));

        
        return Auth::attempt(['email' => $user->email, 'password' => $user->password]);
    }
    public function getCertificates(Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }
        
        $certificates = DB::table('certificates as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->select('c.*', 'u.name as uploaded_by')
            ->orderBy('c.id', 'DESC');
        if($request->has('status')) {
            $certificates->where('status', $request->status);
        }

        return response()->json($certificates->get());
    }

    public function uploadCertificate(Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }
        //If user is not validator or admin
        if(!(Auth::user()->access_level == 'uploader' || Auth::user()->access_level == 'admin')) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        if($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = str_replace(' ', '-', $file->getClientOriginalName()); //getting filename
            
            $certificate = new Certificate();
            $certificate->user_id = Auth::user()->id;
            $certificate->certificate_type = $request->certificate_type;
            $certificate->filename = $filename;
            $certificate->status = 'queued';
            $certificate->save();

            $file->move(public_path('/uploads/' . $certificate->id . '/pdf/'), $filename);

            saveLog(Auth::user()->id, sprintf('%s Certificate File Uploaded (%s)', strtoupper($certificate->certificate_type), $filename));
            return response()->json($certificate);
        }
    }

    private function validateDate($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
        return $d && $d->format($format) === $date;
    }

    private function getMetaData($keyword) {
        $metadata_keys = [
            [
                'keyword' => 'date',
                'parameter' => 'date'
            ],
            [
                'keyword' => 'certificate-no.',
                'parameter' => 'certificate-no.'
            ],
            [
                'keyword' => 'vessel',
                'parameter' => 'vessel'
            ],
            [
                'keyword' => 'name-of-vessel',
                'parameter' => 'vessel'
            ],
            [
                'keyword' => 'vessel-name',
                'parameter' => 'vessel'
            ],
            // [
            //     'keyword' => 'quantity',
            //     'parameter' => 'quantity'
            // ],
            // [
            //     'keyword' => 'quantity-loaded',
            //     'parameter' => 'quantity-loaded'
            // ],
            [
                'keyword' => 'weight',
                'parameter' => 'weight'
            ],
            [
                'keyword' => 'loadable-quantity',
                'parameter' => 'loadable-quantity'
            ]
        ];

        foreach($metadata_keys as $m) {
            if(preg_match('/' . $m['parameter'] .'/', $keyword)) {
                return $m['parameter'];
            }
        }

        return false;
    }

    private function getCoalData($keyword) {
        $coal_data_keys = [];
        $data_keys = DataKey::where('certificate_type', 'coal')->get();

        foreach($data_keys as $k) {
            $coal_data_keys[] = [
                'keyword' => $k->keyword,
                'parameter' => $k->parameter
            ];
        }

        foreach($coal_data_keys as $c) {
            if(preg_match('/' . $c['keyword'] .'/', $keyword)) {
                return $c['parameter'];
            }
        }

        return false;
    }

    private function getDGAData($keyword) {
        $dga_data_keys = [];
        $data_keys = DataKey::where('certificate_type', 'dga')->get();

        foreach($data_keys as $k) {
            $dga_data_keys[] = [
                'keyword' => $k->keyword,
                'parameter' => $k->parameter
            ];
        }

        foreach($dga_data_keys as $c) {
            if(preg_match('/' . $c['keyword'] .'/', $keyword)) {
                return $c['parameter'];
            }
        }

        return false;
    }

    

    private function getMetaDataValue($keyword, $line) {
        $value = str_replace($keyword, '', strtolower($line));
        return $value;
    }

    public function parseData($id, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        $certificate = Certificate::find($id);
        if($certificate->certificate_type == 'coal') {
            if($certificate->validated) {
                $parameters = CertificateParameter::where('certificate_id', $id);
                return response()->json([
                    'certificate' => $certificate,
                    'parameters' => $parameters->get()
                ]);
            }
            else {
                return response()->json($this->parseCoalData($id));
            }
        }
        else {
            if($certificate->validated) {
                $parameters = CertificateParameter::where('certificate_id', $id);
                return response()->json([
                    'certificate' => $certificate,
                    'parameters' => $parameters->get()
                ]);
            }
            else {
                return response()->json($this->parseDGAData($id));
            }
        }
    }

    public function parseDGAData($id) {
        $lines = $this->getRawData($id);

        $attributes = [];
        $dga_data = [];
        $metadata = [];

        foreach($lines as $key => $l) {
            $line_arr = explode('  ', $l);
            if(count($line_arr)) {
                $attribute = trim($line_arr[0]);
                $attribute = strtolower($attribute);
                $attribute = preg_replace('/\(([^)]+)\)/i', '', $attribute); //Removing additiona words enclosed in parenthesis eg (as received basis)
                $attribute = trim($attribute);
                $attribute = preg_replace('/[^a-z0-9\s\(\)\.\-\|]+/i', '', $attribute); //Replacing special characters eg `parameter
                $attribute = str_replace(' ', '-', $attribute);

                $attribute = strtolower($attribute);


                if(strpos(strtolower($l), 'certificate no.') != null) {
                    $position = strpos(strtolower($l), 'certificate no.');
                    $certificate_no = substr($l, $position + 15); //($position + 15) starting position of the certificate number plus the total length of the word "Certificate No."
                    $certificate_no = preg_replace("/[^A-Z0-9a-z\-]/",'', $certificate_no);
                    $metadata['certificate_number'] = trim($certificate_no);
                }
                if(strpos(strtolower($l), 'sampling date') != null) {
                    $position = strpos(strtolower($l), 'sampling date');
                    $sampling_date = substr($l, $position + 13); //($position + 13) starting position of the certificate number plus the total length of the word "Sampling Date"
                    $sampling_date = str_replace(':', '', $sampling_date);
                    $sampling_date = trim($sampling_date);

                    if(strtotime($sampling_date)) {
                        $metadata['date'] = date('Y-m-d', strtotime($sampling_date));
                    }
                }
                else {
                    $param_cd = $this->getDGAData($attribute);
                    $value = '';
                    foreach($line_arr as $line_idx => $line_data) {
                        $line_data = trim(str_replace([':', ';', ' ', '+', 'kcal/kg', '%'], '', $line_data)); //Removing additional Characters in the Value
                        if(is_numeric($line_data) ) {
                            $value = $line_data;
                            break;
                        }
                    }
                    if(!isset($dga_data[$param_cd])) {
                        if($value) {
                            $dga_data[$param_cd] = $value;
                        }
                    }
                    else {
                        if($value) {
                            $dga_data[$param_cd] = $value;
                        }
                    }
                }
            };
        }

        $configurations = TagConfiguration::where('document_type', 'dga');
        $certificate = Certificate::find($id);
        return [
            'configurations' => $configurations->get(),
            'metadata' => $metadata,
            'data' => $dga_data,
            'certificate' => $certificate
        ];

    }

    public function parseCoalData($id) {
        $property = [
            'calorific-value',
            'gross-calorific-value',
            'total-moisture',
            'volatile-matter',
            'total-sulphur',
            'sulfur',
            'ash',
            'phosporous',
            'phosphorous',
            'phosphorus',
            'chlorine',
            'sodium-in-ash',
            'sodium',
        ];

        $ash_fushion_temp = [
            'initial-deformation',
            'hemispherical-deformation',
            'spherical-deformation',
            'flow',
            'initial-deformation-temperature',
            'hemispherical-deformation-temperature',
            'spherical-deformation-temperature',
            'flow-temperature'
        ];

        $ash_analysis = [
            'sio2',
            'si02', //if Letter "O" is converted into Zero
            'al2o3', 
            'al203', //if Letter "O" is converted into Zero
            'fe2o3',
            'fe203', //if Letter "O" is converted into Zero 
            'cao', 
            'ca0',  //if Letter "O" is converted into Zero
            'na2o', 
            'na20',  //if Letter "O" is converted into Zero
            'k2o', 
            'k20',  //if Letter "O" is converted into Zero
            'tio2', 
            'ti02',  //if Letter "O" is converted into Zero
            'mn3o4', 
            'mn304',  //if Letter "O" is converted into Zero
            'p2o5', 
            'p205',  //if Letter "O" is converted into Zero
            'bao', 
            'ba0',  //if Letter "O" is converted into Zero
            'so3', 
            's03',  //if Letter "O" is converted into Zero
            'ho'
        ];

        $lines = $this->getRawData($id);

        $metadata = [];
        $coal_data = [];

        foreach($lines as $key => $l) {
            $line_arr = explode('   ', $l);
            if(count($line_arr)) {
                if(preg_match('/[X\-].+MM/', trim(strtoupper($line_arr[0])), $output)) {
                    $attribute = (string) trim('0' . $output[0]); // Fixing issues eg. 0xX50mm, Ox50mm, Ox50 mm
                    $attribute = str_replace(' ', '', $attribute);
                    $attribute = str_replace('-', 'X', $attribute);
                    $attribute = strtolower($attribute);
                    $attribute = preg_replace('/\(([^)]+)\)/i', '', $attribute);
                    $attribute = preg_replace('/[^a-z0-9\s\(\)\.\-\|]+/i', '', $attribute);
                }
                else {
                    $attribute = trim($line_arr[0]);
                    $attribute = strtolower($attribute);
                    $attribute = preg_replace('/\(([^)]+)\)/i', '', $attribute); //Removing additiona words enclosed in parenthesis eg (as received basis)
                    $attribute = trim($attribute);
                    $attribute = preg_replace('/[^a-z0-9\s\(\)\.\-\|]+/i', '', $attribute); //Replacing special characters eg `parameter
                    $attribute = str_replace(' ', '-', $attribute);

                    if(in_array($attribute, $ash_analysis)) {
                        $attribute = str_replace('0', 'o', $attribute);
                    }

                    $attribute = strtolower($attribute);
                }

                $param_md = $this->getMetaData($attribute);
                
                if($param_md) {
                    if($param_md == 'certificate-no.') {
                        $certno = str_replace(' ', '', $l);
                        $certno = strtolower($certno);
                        $certno = str_replace(['certificateno.', 'certificateno'], '', $certno);
                        $certno = trim($certno);
                        $certno = strtoupper($certno);
                        $certno = mb_convert_encoding($certno, 'UTF-8', 'UTF-8');

                        if(isset($metadata['certificate_number'])) {
                            if(!in_array($certno ,explode('|', $metadata['certificate_number']))) {
                                $metadata['certificate_number'] .= '|' . $certno;
                            }
                        }
                        else {
                            $metadata['certificate_number'] = $certno;
                        }
                    }
                    elseif($param_md == 'date') {
                        if(!isset($metadata['date'])) {
                            $date = strtolower($l);
                            $date = str_replace(' ', '', $date);
                            $position = strpos($date, 'date') + 4; // get start and end of date keyword
                            $date = substr(trim($date), $position);
                            $date = str_replace([':'], '', $date);
                            $date = mb_convert_encoding($date, 'UTF-8', 'UTF-8');

                            if(strtotime($date)) {
                                $metadata['date'] = date('Y-m-d', strtotime($date)); 
                            }
                        }
                    }
                    elseif($param_md == 'vessel') {
                        if(!isset($metadata['vessel'])) {
                            $vessel = strtolower($l);
                            $vessel = str_replace(' ', '-', $vessel);
                            $position = strpos($vessel, 'date') + 6; // get start and end of vessel keyword
                            $vessel = substr(trim($vessel), $position);
                            $vessel = str_replace([':'], '', $vessel);
                            $vessel = mb_convert_encoding($vessel, 'UTF-8', 'UTF-8');
                            
                            $metadata['vessel'] = ucwords(str_replace('-', ' ', $vessel)); 
                        }
                    }
                //    $metadata[] = $l;
                }

                $param_cd = $this->getCoalData($attribute);
                
                if($param_cd) {
                    if(in_array($param_cd, $property)) {
                        $type = '';
                        foreach($line_arr as $line_idx => $line_data) {
                            $line_data = trim(str_replace([':', ';', ' ', '+', 'kcal/kg', '%', '>', ',', '$'], '', $line_data)); //Removing additional Characters in the Value
                            if(is_numeric($line_data) ) {
                                $value = $line_data;
                                break;
                            }
                        }

                        if(strpos(strtolower($l), ' ar ') != null) { //As received
                            $type = 'arb';
                        }
                        elseif(strpos(strtolower($l), '(ar)') != null) { //As received
                            $type = 'arb';
                        }
                        elseif(strpos(strtolower($l), 'as received') != null) { //As received
                            $type = 'arb';
                        }
                        elseif(strpos(strtolower($l), ' adb ') != null) { //Air Dried
                            $type = 'adb';
                        }
                        elseif(strpos(strtolower($l), '(adb)') != null) { //Air Dried
                            $type = 'adb';
                        }
                        elseif(strpos(strtolower($l), 'air dried basis') != null) { //Air Dried
                            $type = 'adb';
                        }
                        
                        $coal_data[$param_cd . '-' . $type] = $value;
                    }
                    else {
                        $value = '';
                        foreach($line_arr as $line_idx => $line_data) {
                            $line_data = trim(str_replace([':', ';', ' ', '+', 'kcal/kg', '%'], '', $line_data)); //Removing additional Characters in the Value
                            if(is_numeric($line_data) ) {
                                $value = $line_data;
                                break;
                            }
                        }
                        if(!isset($coal_data[$param_cd])) {
                            if($value) {
                                $coal_data[$param_cd] = $value;
                            }
                        }
                        else {
                            if($value) {
                                $coal_data[$param_cd] = $value;
                            }
                        }
                        
                    }
                }
            };
        }

        $configurations = TagConfiguration::where('document_type', 'coal');
        $certificate = Certificate::find($id);
        return [
            'configurations' => $configurations->get(),
            'metadata' => $metadata,
            'data' => $coal_data,
            'certificate' => $certificate
        ];
    }

    public function getRawData($id) {
        $path = public_path('/uploads/' . $id . '/extracted');
        $files = scandir($path);

        $contents = '';
        foreach($files as $idx => $f) {
            if($idx > 1) {
                $contents .= file_get_contents($path . '/' . $f);
            }
        }

        $lines = explode("\n", $contents);

        return $lines;
    }

    public function validateData($id, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        //If user is not validator or admin
        if(!(Auth::user()->access_level == 'validator' || Auth::user()->access_level == 'admin')) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        $params = json_decode($request->getContent());
        $datetime = date('Y-m-d', strtotime($params->metadata->date));

        $certificate = Certificate::find($id);
        if($certificate->certificate_type == 'coal') {
            $certificate->vessel_name = $params->metadata->vessel;
        }

        $certificate->certificate_number = $params->metadata->certificate_number;
        $certificate->certificate_date = $datetime;
        $certificate->status = 'validated';
        $certificate->validated = true;
        $certificate->validated_by = 1;
        $certificate->validated_at = date('Y-m-d H:i:s');
        $certificate->save();

        $response = [];
        foreach($params->data as $data) {
            $parameter = new CertificateParameter();
            $parameter->certificate_id = $id;
            $parameter->parameter = $data->parameter;
            $parameter->tagname = $data->tagname;
            $parameter->timestamp = $datetime;
            $parameter->value = $data->value;
            $parameter->save();

            $response[] = $parameter;
        }

        saveLog(Auth::user()->id, sprintf('Certificate Validated (%s)', $certificate->certificate_number));

        return response()->json($response);
    }

    public function writeToPI($id, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        //If user is not validator or admin
        if(!(Auth::user()->access_level == 'validator' || Auth::user()->access_level == 'admin')) {
            return reponse()->json(['message' => 'Unauthorized'], 401);
        }

        $params = json_decode($request->getContent());
        $piwebapi = new PIWebAPIController();

        $response = [];
        foreach($params->data as $d) {
            if(isset($d->value) && $d->value) {
                $response[] = $piwebapi->writeTagValue($d->tagname, $d->value, $d->timestamp);
            }
        }

        $certificate = Certificate::find($id);
        $certificate->status = 'archived';
        $certificate->save();

        saveLog(Auth::user()->id, sprintf('Certificate (%s) has been Written to PI', $certificate->certificate_number));

        return response()->json($response);
    }

    public function updateData($id, Request $request) {
        if(!$this->isAuthenticated($request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if(Auth::user()->access_level == 'uploader') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $params = json_decode($request->getContent());

        $certificate = Certificate::find($id);
        $certificate->certificate_date = date('Y-m-d', strtotime($params->metadata->certificate_date));
        $certificate->certificate_number = $params->metadata->certificate_number;
        $certificate->vessel_name = $params->metadata->vessel_name;
        $certificate->save();

        foreach($params->data as $d) {
            $parameter = CertificateParameter::find($d->id);
            $parameter->value = $d->value;
            $parameter->parameter = $d->parameter;
            $parameter->tagname = $d->tagname;
            $parameter->timestamp = date('Y-m-d 01:00:00', strtotime($params->metadata->certificate_date));;
            $parameter->save();
        }

        $parameters = CertificateParameter::where('certificate_id', $id);

        saveLog(Auth::user()->id, sprintf('Certificate %s has been updated', $certificate->certificate_number));
        return response()->json([
            'certificate' => $certificate,
            'parameters' => $parameters->get()
        ]);
    }
}
