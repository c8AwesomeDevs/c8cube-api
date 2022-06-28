<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\WebApiLog;

class PIWebAPIController extends Controller
{
    protected $piwebapi;
    protected $piwebapi_username;
    protected $piwebapi_password;
    protected $pi_server;

    public function __construct() {
        // PIWEBAPI
        // PIWEBAPI_USERNAME
        // PIWEBAPI_PASSWORD
        // PISERVER
        $this->piwebapi = env('PIWEBAPI');
        $this->piwebapi_username = env('PIWEBAPI_USERNAME');
        $this->piwebapi_password = env('PIWEBAPI_PASSWORD');
        $this->pi_server = env('PISERVER');
    }
    
    public function getRequest($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt($ch, CURLOPT_USERPWD, $this->piwebapi_username . ":" . $this->piwebapi_password);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $response = ['code' => $httpcode,'data' => $output];

        $log = new WebApiLog();
        $log->api_method = 'GET';
        $log->api_url = $url;
        $log->api_request = '{}';
        $log->api_response = $output;
        $log->save();

        return $response;
    }

    public function postRequest($url, $data) {
        $ch = curl_init();        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, 
            array('Content-Type:application/json', 'X-Requested-With:XMLHttpRequest')
           );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_USERPWD, $this->piwebapi_username . ":" . $this->piwebapi_password);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $output = curl_exec($ch);
        curl_close($ch);

        $response = ['code' => $httpcode,'data' => json_decode($output)];

        $log = new WebApiLog();
        $log->api_method = 'POST';
        $log->api_url = $url;
        $log->api_request = json_encode($data);
        $log->api_response = $output;
        $log->save();

        return $response;
    }

    public function getTagDetails($tagname) {
        $url = $this->piwebapi . '/points?path=\\\\' . $this->pi_server . '\\' . $tagname;
        $tagDetails = $this->getRequest($url);

        return $tagDetails;
    }

    public function createTag($tagname, $dataType, $pointsource) {
        $tagDetails = [
            'Name' => $tagname,
            'PointType' => $dataType,
            'PointClass' => 'base',
        ];
        $data = [
            1 => [
                "Method" => "GET",
                "Resource" => $this->piwebapi . '/dataservers?name=' . $this->pi_server
            ],
            2 => [
                "Method" => "POST",
                "Resource" => "$.1.Content.Links.Points",
                "Content" => json_encode($tagDetails),
                "ParentIds" => ["1"]
            ]
        ];

        $url = $this->piwebapi . '/batch';

        return $this->postRequest($url, $data);
    }

    public function writeTagValue($tag, $value) {
        $tagValue = [
            'Timestamp' => date('Y-m-d\TH:i:s\Z'),
            'Value' => $value
        ];

        $data = [
            1 => [
                "Method" => "GET",
                "Resource" => $this->piwebapi . '/points?path=\\\\' . $this->pi_server . '\\' . $tag
            ],
            2 => [
                "Method" => "POST",
                "Resource" => "$.1.Content.Links.Value",
                "Content" => json_encode($tagValue),
                "ParentIds" => ["1"]
            ]
        ];

        $url = $this->piwebapi . '/batch';

        $response = [
            'tag' => $tag,
            'result' => $this->postRequest($url, $data)
        ];

        return $response;
    }

     public function test() {

        $data = [
            1 => [
                "Method" => "GET",
                "Resource" => $this->piwebapi . '/points?path=\\\\' . $this->pi_server . '\\sinusoid'
            ],
            2 => [
                "Method" => "GET",
                "Resource" => $this->piwebapi . '/points?path=\\\\' . $this->pi_server . '\\sinusoid'
            ]
        ];

        $url = $this->piwebapi . '/batch';


        $response = [
            'result' => $this->postRequest($url, $data)
        ];

        return response()->json($response);
    }
}
