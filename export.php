<?php

include "./PHPExcel-1.8/Classes/PHPExcel.php";
$spreadsheet = new PHPExcel();

if (!$_GET["debug"]) {
	$server = $_GET["server"];
	$port = $_GET["port"];
	$userid = $_GET["userid"];
	$userpass = $_GET["userpass"];
  $entityType = $entityType;
  $entityId = $_GET["entityId"];
  $keys = $_GET["keys"];
  $startTs = $_GET["startTs"];
  $endTs = $_GET["endTs"];
  $interval = $_GET["interval"];
  $limit = $_GET["limit"];
  $agg = $_GET["agg"];
}
else {
	$server = "iot.aphese.kr";
	$port = 8080;
	$userid = "test@test.com";
	$userpass = "pass";
	$entityType = "DEVICE";
	$entityId = "d02c10b0-3edf-11eb-895a-4f03e5f266ed";
	$keys = "ZE25_O3";
	$startTs = "1608287520000";
	$endTs = "1608287700000";
	$interval = "1000";
	$limit = "100";
	$agg = "AVG";
} 

/******* Getting JWT_TOKEN *******/

/* curl -X POST "http://iot.aphese.kr:8080/api/auth/login" ^
	--header "Content-Type: application/json" ^
	--header "Accept: application/json" ^
	-d "{\"username\":\"test@test.com\", \"password\":\"pass\"}"
*/

$url = "http://".$server.":".$port."/api/auth/login";

$ch = curl_init();                                 //curl 초기화
curl_setopt($ch, CURLOPT_URL, $url);               //URL 지정하기
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Accept: application/json'
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);    //요청 결과를 문자열로 반환 
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);      //connection timeout 10초 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);   //원격 서버의 인증서가 유효한지 검사 안함
curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"username\":\"" . $userid . "\", \"password\":\"".$userpass."\"}");
curl_setopt($ch, CURLOPT_POST, true);              //true시 post 전송 
 
$response = curl_exec($ch);
curl_close($ch);

$data_array = json_decode($response, true);
$token = $data_array["token"];
/******* Getting Telemetry data *******/
/*
curl "http://iot.aphese.kr:8080/api/plugins/telemetry/DEVICE/d02c10b0-3edf-11eb-895a-4f03e5f266ed/values/timeseries?keys=ZE25_O3&startTs=1608287520000&endTs=1608287700000&interval=1000&limit=100&agg=AVG" ^
-H "Content-Type:application/json" ^
-H "X-Authorization: Bearer *******"
*/

$url = "http://".$server.":".$port."/api/plugins/telemetry/".$entityType."/".$entityId."/values/timeseries?keys=".$keys."&startTs=".$startTs."&endTs=".$endTs."&interval=".$interval."&limit=".$limit."&agg=".$agg;

$ch = curl_init();                                 //curl 초기화
curl_setopt($ch, CURLOPT_URL, $url);               //URL 지정하기
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
	'X-Authorization: Bearer '.$token
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);    //요청 결과를 문자열로 반환 
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);      //connection timeout 10초 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);   //원격 서버의 인증서가 유효한지 검사 안함
 
$json = curl_exec($ch);
curl_close($ch);

/******* Converting Returned json values to CSV  *******/

$array = json_decode($json, true);
$f = fopen('php://output', 'w');

$sheetId = 0;
foreach ($array as $key_name => $key_name_value){
	$spreadsheet -> createSheet(NULL,$sheetId);
	$spreadsheet -> setActiveSheetIndex($sheetId)
		-> setCellValue("A1", "ts")
		-> setCellValue("B1", "value")
		-> setTitle($key_name);
	foreach ($key_name_value as $no => $no_value){
		$ts = (int)$no_value['ts'];
		$value = (double)$no_value['value'];
		$spreadsheet -> getActiveSheet()
			-> setCellValueExplicit(sprintf("A%s", $no+2), $ts, PHPExcel_Cell_DataType::TYPE_NUMERIC)
			-> setCellValueExplicit(sprintf("B%s", $no+2), $value, PHPExcel_Cell_DataType::TYPE_NUMERIC);
	}
	$sheetId++;
}

$spreadsheet -> setActiveSheetIndex(0);  //기본으로 열리는 시트를 1번시트로 지정

$filename = iconv("UTF-8", "EUC-KR", "tb_export.xls");  //한글도 지원하기 위해 iconv 사용

header("Content-Type:application/vnd.ms-excel");
header("Content-Disposition: attachment;filename=".$filename.".xls");
header("Cache-Control:max-age=0");

$objWriter = PHPExcel_IOFactory::createWriter($spreadsheet, "Excel5");
$objWriter -> save("php://output");
/*

$firstLineKeys = false;
foreach ($array as $line)
{
       if (empty($firstLineKeys))
       {
           $firstLineKeys = array_keys($line);
           fputcsv($f, $firstLineKeys);
           $firstLineKeys = array_flip($firstLineKeys);
       }
       // Using array_merge is important to maintain the order of keys acording to the first element
       fputcsv($f, array_merge($firstLineKeys, $line));
}*/

?>
