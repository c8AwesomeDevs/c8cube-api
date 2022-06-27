<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use NcJoes\PopplerPhp\PdfInfo;
use NcJoes\PopplerPhp\Config;
use NcJoes\PopplerPhp\PdfToCairo;
use NcJoes\PopplerPhp\PdfToHtml;
use NcJoes\PopplerPhp\Constants as C;
use thiagoalessio\TesseractOCR\TesseractOCR;
use App\Certificate;

class OCRController extends Controller
{
    public function __init() {
        Config::setBinDirectory('C:/Program Files/poppler-0.67.0/bin');
    }

    public function pdfToImage() {
        $pdf = new Pdf(public_path('/uploads/pdf/test.pdf'));
        $pdf->setOutputFormat('png');
        $pdf->saveImage(public_path('/uploads/image'));
    }
    public function convert($id) {
        $certificate = Certificate::find($id);

        $pdfPath = public_path('/uploads/' . $certifcate->id . '/pdf/' . $certificate->filename);
        $pdf = new PdfInfo($pdfPath);
        $info = $pdf->getInfo(); //returns an associative array
        $authors = $pdf->getAuthors();

        $cairo1 = new PdfToCairo($pdfPath);

        // $cairo1->startFromPage(1)->stopAtPage((int)$info['pages']);
        $cairo1->startFromPage(1)->stopAtPage($info['pages']);
        $cairo1->generatePNG();

        return "Success";
    }

    public function coalProcess($content = null) {
        return $content;
        $lines = explode("\n", $content);
        $keywords = [
            // 'Vessel',
            // 'Date',
            'Certificate No.',
            'Weight',
            'Calorific Value',
            'Total Moisture',
            'Ash',
            'Volatile Matter',
            'Total Sulfur',
            'Phosphorus',
            'Chlorine',
            'Sodium in Ash',
            'Fixed Carbon',
            'Fuel Ratio',
            'Total Sulfur',
            'Carbon',
            'Hydrogen',
            'Nitrogen',
            'Sulfur',
            'Oxygen',
            'Initial Deformation',
            'Hemispherical Deformation',
            'Flow',
            '0 x 50 mm',
            '0 x 3 mm',
            '0 x 2 mm',
            '0 x 0.5 mm',
            
        ];

        $filteredLines = [];

        foreach($lines as $l) {
            foreach($keywords as $idx => $keyword) {
                $rawLine = str_replace(' ', '', strtolower($l));
                $keyword = str_replace(' ', '', strtolower($keyword));

                if(strpos($rawLine, $keyword) !== false) {
                    $filteredLines[] = $l;
                }
            }
        }
        
        print_r($filteredLines);die;
    }

    
}
