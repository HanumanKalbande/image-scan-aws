<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
set_time_limit(0);
require_once ('/vendor/autoload.php');
include_once('_function.php');
require_once('credentials.php');
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use thiagoalessio\TesseractOCR\TesseractOCR;

try{
$s3client = new S3Client([
    'version'     => 'latest',
    'region'      => $region,  //Region of the bucket
    'credentials' => array(
        'key'    => $key,
        'secret' => $secret,
    )
]);
}
catch(AwsException $e) {
    echo $e->getMessage();
}
//echo shell_exec('tesseract --version'); exit;
$listOfImages = ['sample-page1-1.pdf~01.png'];
$bucket_name ='pdf-raw-2141';
$dir = sys_get_temp_dir().'/';

foreach($listOfImages as $pngFilename){
    $cmd = $s3client->getCommand('GetObject', [
        'Bucket' => $bucket_name,
        'Key' => "raw/".$pngFilename,
        'ResponseContentDisposition' => 'attachment; filename="'.$pngFilename.'"',
    ]);
    $request = $s3client->createPresignedRequest($cmd,'+5 minutes');
    $file_secure_url_download = (string)$request->getUri();

    // download image
    downloadDoc($pngFilename, $file_secure_url_download, $dir);
    echo 'The image has been downloaded to the path:-> '.$dir.''.$pngFilename ."\n";

    if(file_exists($dir.$pngFilename)):
        $perPage = $dir.$pngFilename;
        $a = explode("~",$pngFilename);
        $pageNo = str_replace(".png","",$a[1]);
        //echo $pageNo = str_replace(".png", "", $a[sizeof($a)-1]) ; // same as above, it's gives same output
        $pdfFilename = $a[0];

        $scan_data = scanImages($perPage, $pageNo, $pdfFilename, $pngFilename);
        if(!empty($scan_data)):
            print_r($scan_data);
            //unlink early scaned image
            unlink($perPage);
            echo "scanning process done for ". $pngFilename . "\n";
        else:
            echo "image has empty. \n";
        endif;   
        
                        
    else:
        echo "file not found in the system tmp folder \n";
    endif;
}

exit();
?>
 