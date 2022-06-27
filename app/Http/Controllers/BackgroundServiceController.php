<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Certificate;

class BackgroundServiceController extends Controller
{
    public function getCertificates(Request $request) {
        $certificates = Certificate::orderBy('id', 'ASC');
        if($request->has('status')) {
            $certificates->where('status', $request->status);
        }

        return response()->json($certificates->get());
    }

    public function updateStatus($id, Request $request) {
        $certificate = Certificate::find($id);
        $certificate->status = $request->status;
        $certificate->save();

        return response()->json($certificate);
    }
}
