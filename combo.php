<?php
/**
 *	作者: 拔赤 - lijine00333@163.com
 *  修改：马立铭 - maliming@cuc.edu.cn
 */

date_default_timezone_set("Asia/Shanghai");

$LOCAL  = "./";
$CDN    = "http://g.tbcdn.cn/kissy/k/1.4.0/";

function err_handler() {
	throw new Exception();
	exit;
}
set_error_handler("err_handler");

// 处理请求中附带的文件列表，得到原始数据
$split_a = explode("??", $_SERVER['REQUEST_URI']);

// 原始请求文件数组
$files = array();
if (isset($split_a[1]) && $split_a[1]) {
	if (preg_match("/,/", $split_a[1])) {
		$_tmp = explode(',', $split_a[1]);
		foreach ($_tmp as $v) {
			$files[] = $v;
		}
	}
	else {
		$files[] = $split_a[1];
	}
}
else {
	header("Status: 404 Not Found", true, 404);
	exit;
}

// 文件的最后修改时间
$last_modified_time = 0;
//过滤后的待抓取的文件列表
$a_files = array();
foreach ($files as $k) {
	$k = preg_replace(array("/^\\//", "/\\?.+$/"), array('', ''), $k);

	if (!preg_match("/(.js|.css)$/", $k)) {
		continue;
	}
	else {
		$a_files[] = $k;

		if (file_exists($LOCAL.$k)) {
			$filemtime = filemtime($LOCAL.$k);
			if ($filemtime && ($filemtime > $last_modified_time)) {
				$last_modified_time = $filemtime;
			}
		}
	}
}

// 检查请求头的if-modified-since，判断是否304
$request_headers = getallheaders();
if (isset($request_headers['If-Modified-Since']) && (strtotime($request_headers['If-Modified-Since']) == $last_modified_time)) {
	// 如果客户端带有缓存
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified_time)." GMT", true, 304);
	exit;
}

// 文件类型
$type = '';
// 线上未找到的文件
$unfound = array();
// 输出结果使用的数组
$R_files = array();
foreach ($a_files as $k) {
	if (file_exists($LOCAL.$k)) {
		$R_files[] = file_get_contents($LOCAL.$k);
	}
	else {
		try {
			$R_files[] = "/* Fetch: ".$CDN.$k." */\n".file_get_contents($CDN.$k);
		}
		catch (Exception $e) {
			$unfound[] = $k;
			continue;
		}
	}

	if (empty($type)) {
		$arr = explode('.', $k);
		$type = end($arr);
	}
}

//添加过期头，过期时间1年
header("Expires: ".date("D, j M Y H:i:s", strtotime("now + 1 years"))." GMT");
header("Cache-Control: max-age=315360000");
header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_modified_time)." GMT");

//输出文件类型，各种可能的文件类型
$header = array(
	"js"    => "Content-Type: application/javascript",
	"css"   => "Content-Type: text/css",
	"jpg"   => "Content-Type: image/jpg",
	"gif"   => "Content-Type: image/gif",
	"png"   => "Content-Type: image/png",
	"jpeg"  => "Content-Type: image/jpeg",
	"swf"   => "Content-Type: application/x-shockwave-flash"
);
if (isset($header[$type])) {
	header($header[$type]);
}

//拼装文件
echo join("\n", $R_files);

if (!empty($unfound)) {
	if (!empty($R_files)) {
		echo "\n";
	}
	echo "/* non published files:\n";
	echo join("\n", $unfound);
	echo "\n*/";
}
?>
