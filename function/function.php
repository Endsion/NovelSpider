<?php

//if (!function_exists('category_maps')) {
/***phpQuery 编码转换***/
function strIosToUtf8($str){
	if(is_string($str) || !empty($str)){
		return mb_convert_encoding($str,'ISO-8859-1','utf-8');
	}
	return '';
}
/***日志***/
function write_log($data){ 
	date_default_timezone_set('PRC'); 
	$years = date('Ymd/H');
	//设置路径目录信息
	$url = APP_PATH.SITE_PATH.'/log/'.$years.'_request_log.txt';
	$dir_name=dirname($url);
	//目录不存在就创建
	if(!file_exists($dir_name)){
		//iconv防止中文名乱码
		$res = mkdir(iconv("UTF-8", "GBK", $dir_name),0777,true);
	}
	$data = date('Y-m-d H:i:s',time()).':'.$data;
	$fp = fopen($url,"a");//打开文件资源通道 不存在则自动创建
	//加锁 lock_ex 读锁  lock_sh 写锁 非堵塞 LOCK_NB
	if(flock($fp, LOCK_EX | LOCK_NB)){    
		fwrite($fp,$data."\r\n");//写入文件
		flock($fp, LOCK_UN);  // 释放锁定
	}
	fclose($fp);//关闭资源通道
}
function category_maps($key){
	$category_maps = [
		'玄幻' => 'xuanhuan',
		'修真' =>'xiuzhen',
		'都市' => 'dushi',
		'历史' => 'lishi',
		'网游' => 'wangyou',
		'科幻'=> 'kehuan',
		'穿越'=> 'chuanyue',
		'文学名著' => 'mingzhu',
		'其他' => 'other'
	];
	if(array_key_exists($key,$category_maps)){
		return $category_maps[$key];
	}else{
		return 'other';
	}
}