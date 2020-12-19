<?php
include "./PHPExcel-1.8/Classes/PHPExcel.php";
$spreadsheet = new PHPExcel();
$timezone = +9;  // Asia/Seoul GMT+9

$server = $_GET["server"];
$port = $_GET["port"];
$userid = $_GET["userid"];
$userpass = $_GET["userpass"];
$entityType = $_GET["entityType"];
$entityId = $_GET["entityId"];
$keys = $_GET["keys"];
$startTs = strtotime($_GET["startTs"])*1000-(60*60*$timezone*1000);
$endTs = strtotime($_GET["endTs"])*1000-(60*60*$timezone*1000);
$interval = $_GET["interval"];
$limit = $_GET["limit"];
$agg = $_GET["agg"];

/******* Getting JWT_TOKEN *******/

/* curl -X POST "http://{server}:{port}/api/auth/login" ^
	--header "Content-Type: application/json" ^
	--header "Accept: application/json" ^
	-d "{\"username\":\"{userid}\", \"password\":\"{userpass}\"}"
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
curl "http://{server}:{port}:8808/api/plugins/telemetry/{entityType}/{entityId}/values/timeseries?keys={keys}&startTs={startTs}&endTs={endTs}&interval={interval}&limit={limit}&agg={AVG}" ^
-H "Content-Type:application/json" ^
-H "X-Authorization: Bearer {token}"
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

$filename = iconv("UTF-8", "EUC-KR", "tb_export.xlsx");  //한글도 지원하기 위해 iconv 사용

header("Content-Type:application/vnd.ms-excel");
header("Content-Disposition: attachment;filename=".$filename);
header("Cache-Control:max-age=0");

$objWriter = PHPExcel_IOFactory::createWriter($spreadsheet, "Excel2007");
$objWriter -> save("php://output");

?>
