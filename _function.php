<?php
require '/vendor/autoload.php';
use thiagoalessio\TesseractOCR\TesseractOCR;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
set_time_limit(0);

function downloadDoc($fileName, $file_secure_url_download, $dir = './') {
    try {
        //Initialize the cURL session
        $ch = curl_init($file_secure_url_download);
        $save_file_loc = $dir . $fileName;
        if(file_exists($save_file_loc)) {
            unlink($save_file_loc);
        }
        $fp = fopen($save_file_loc, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }
    catch(Exception $e) {
        echo 'Message: ' .$e->getMessage();
    }
}
/**
 * Use png images to scan for text and coordinate data with Tesseract OCR
 */
function scanImages($pdf_image, $a, $pdfFilename, $listOfImage){
    $pageContent = [];
    $coordinatesArray = [];    
    $ocr = new TesseractOCR(); 
    $b = urldecode($pdf_image);
    
    // check if the images is present in the temp folder and do scan if it is
    if($b != '') {  
                $ocr->image($b);
                $ocr->config('tessedit_create_hocr', 1) ;  
                $ocr->config('hocr_font_info', 1) ;
                $fileContent = $ocr->hocr()->run();  
                $c = strip_tags($fileContent);               
                $pageContent[] = $c;

                if($fileContent !=''){   
                    $coordinatesArray = getCoordinatesBasedOnText($fileContent, $a, urldecode($pdfFilename));
                }

        //showing output of image file
        $jsonContent = '';
        $coordinatesObject = '';
        if(sizeof($pageContent) > 0) {
            $jsonContent = stripcslashes(json_encode($pageContent, JSON_FORCE_OBJECT)); 
        }
        if(sizeof($coordinatesArray) > 0) {
            $identifier = rand(1000,9999);
            $returnData['id'] = $pdfFilename.'_'.$a.'_'. $identifier;
            $returnData['pdfname'] = $pdfFilename;
            $returnData['imagename'] = './'.urldecode($listOfImage);
            $returnData['page'] = $a;
            $returnData['jsoncontent'] = $coordinatesArray;
            $coordinatesObject = json_encode($returnData, JSON_FORCE_OBJECT);
        }
    } else {
        $coordinatesObject = json_encode(array('fault'=>'No scan data returned'));
    }
    return  $coordinatesObject;
}
/**
 * Process coordinates from scanned text
 */
function getCoordinatesBasedOnText($content, $page, $pdfFilename) {
    $doc = new DOMDocument();    
    $doc->loadHtml($content);     
    $pageDimension = [];
    $xpath = new DOMXPath($doc);
    $pgdim = $xpath->query('//*[@class="ocr_page"]');    
    $paragraph = $xpath->query('//*[@class="ocr_par"]');
    $word = $xpath->query('//*[@class="ocrx_word"]');

    foreach ($pgdim as $link) {
        $pageDimension = explode(";", $link->getAttribute('title')); 
        break;  
    }

    if(!empty($pageDimension)) {
        $pd = explode(" ", $pageDimension[1]);
        if(isset($pd[4]) && isset($pd[5]))
            $pageDim = array($pd[4], $pd[5]);
        else {
            $pageDim = array(0, 0);
        }

    } else {
        $pageDim = array(0, 0);
    }
    
    $coordinatesArray = [];
    $i = 0;
    
    foreach ($paragraph as $link) {       
        $spanAttributes = explode(";", $link->getAttribute('title'));

        if(!empty($spanAttributes)) {
            $coordinatesData = explode(" ",$spanAttributes[0]);

            if(!empty($coordinatesData)) {
                $coordinatesArray[$i]['x'] = $coordinatesData[1] ?? 0;
                $coordinatesArray[$i]['y'] = $coordinatesData[2] ?? 0;
                $coordinatesArray[$i]['width'] = $coordinatesData[3] ?? 0;
                $coordinatesArray[$i]['height'] = $coordinatesData[4] ?? 0;
                $coordinatesArray[$i]['page'] = $page ?? 0;
                $coordinatesArray[$i]['para'] = $link->nodeValue ?? '';
                $coordinatesArray[$i]['page_dim'] = $pageDim;
                $i++;
            }
        }       
    }
    foreach($word as $link) {       
            $spanAttributes = explode(";", $link->getAttribute('title'));

            if(!empty($spanAttributes)) {
                $coordinatesData = explode(" ",$spanAttributes[0]);
                $fontSize = $spanAttributes[2];
                $sizeInpt = explode(" ", $fontSize);               
                $cal = floatval($sizeInpt[2]);

                if(!empty($coordinatesData)) {
                    $coordinatesArray[$i]['x'] = $coordinatesData[1] ?? 0;
                    $coordinatesArray[$i]['y'] = $coordinatesData[2] ?? 0;
                    $coordinatesArray[$i]['width'] = $coordinatesData[3] ?? 0;
                    $coordinatesArray[$i]['height'] = $coordinatesData[4] ?? 0;
                    $coordinatesArray[$i]['page'] = $page ?? 0;
                    $coordinatesArray[$i]['text'] = $link->nodeValue ?? '';
                    $coordinatesArray[$i]['conf'] = substr($spanAttributes[1], 9) ?? 0;
                    $coordinatesArray[$i]['font_size_px'] = $cal * 1.33;
                    $coordinatesArray[$i]['font_size_width_px'] = ($cal * strlen($link->nodeValue))/2;
                    $coordinatesArray[$i]['font_size_height_px'] = $cal;
                    $coordinatesArray[$i]['page_dim'] = $pageDim;
                    $i++;
                }
            }       
    }
    return $coordinatesArray;
}
?>