<?php
class Curl {
	/***get请求***
	***$url 请求链接**
	***返回请求数据***
	**/
	public static function httpGet($url){
		$c = curl_init(); 
		$ip = self::getRandIp(); //每次请求都切换ip防止被封
		$header = array( 
			'CLIENT-IP:'.$ip,
			'X-FORWARDED-FOR:'.$ip
		); 
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_HTTPHEADER, $header); 
		curl_setopt($c,CURLOPT_USERAGENT,"Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");
		curl_setopt($c, CURLOPT_HTTPHEADER,array('Accept-Encoding: gzip, deflate'));
		curl_setopt($c, CURLOPT_ENCODING, 'gzip,deflate');//这个是解释gzip内容.................
		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false); //不验证证书
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false); //不验证证书
		curl_setopt($c, CURLOPT_HTTPPROXYTUNNEL, true); //这个参数用于通过http代理来走其它协议，比如ftp协议，这时http协议完全变成tunnel（管道的意思）。

		curl_setopt($c,CURLOPT_CONNECTTIMEOUT,10); 
		curl_setopt($c, CURLOPT_TIMEOUT,2000000);
		$contents = curl_exec($c); 
		$contents = mb_convert_encoding($contents, 'utf-8', 'GBK,UTF-8,ASCII');
		curl_close($c);
		return $contents;
	}
	public static function rabbitPost($url){
		$c = curl_init(); 
		$ip = self::getRandIp(); //每次请求都切换ip防止被封
		$header = array(
		); 
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_HTTPHEADER, $header); 
		curl_setopt($c,CURLOPT_USERAGENT,"Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");
		//curl_setopt($c, CURLOPT_HTTPHEADER,array('Accept-Encoding: gzip, deflate'));
		curl_setopt($c, CURLOPT_ENCODING, 'gzip,deflate');//这个是解释gzip内容.................
		curl_setopt($c, CURLOPT_URL, $url);
		curl_setopt($c, CURLOPT_USERPWD, 'guest:guest');
		curl_setopt($c, CURLOPT_POST, 1);
		curl_setopt($c, CURLOPT_TIMEOUT,2);
		$contents = curl_exec($c); 
		$contents = mb_convert_encoding($contents, 'utf-8', 'GBK,UTF-8,ASCII');
		curl_close($c);
		return $contents;
	}
	/***长连接请求***
	***$url 请求链接**
	***返回请求数据***
	**/
	public static function httpLongGet($url){
		$c = curl_init(); 
		$ip = self::getRandIp(); //每次请求都切换ip防止被封
		$header = array( 
			'CLIENT-IP:'.$ip,
			'X-FORWARDED-FOR:'.$ip,
			'Connection: Keep-Alive', //长链接
			'Keep-Alive: 300'  //长链接
		); 
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_HTTPHEADER, $header); 
		curl_setopt($c,CURLOPT_USERAGENT,"Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1");
		curl_setopt($c, CURLOPT_HTTPHEADER,array('Accept-Encoding: gzip, deflate'));
		curl_setopt($c, CURLOPT_FORBID_REUSE, false); //长链接
		curl_setopt($c, CURLOPT_ENCODING, 'gzip,deflate');//这个是解释gzip内容.................
		curl_setopt($c, CURLOPT_URL, $url); 
		curl_setopt($c, CURLOPT_TIMEOUT,2);
		$contents = curl_exec($c); 
		if (curl_errno($c)) {
			echo 'Curl error: ' . curl_error($ch);
		}
		$contents = mb_convert_encoding($contents, 'utf-8', 'GBK,UTF-8,ASCII');
		curl_close($c);
		return $contents;
	}
	/***curl 并发请求
		array($k=>$url) 
		timeout 超时时间
		返回 $k=>返回内容
	***/
	public static function batchCurlHttp($array,$timeout='15'){
		$mh = curl_multi_init();//创建多个curl语柄 
		foreach($array as $k=>$url){    
			$ip = self::getRandIp(); //每次请求都切换ip防止被封
			$header = array( 
				'CLIENT-IP:'.$ip,
				'X-FORWARDED-FOR:'.$ip
			); 
			$conn[$k]=curl_init($url);//初始化  
			//curl_setopt($conn[$k], CURLOPT_TIMEOUT, $timeout);//设置超时时间  
			curl_setopt($conn[$k], CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');  
			curl_setopt($conn[$k], CURLOPT_MAXREDIRS, 7);//HTTp定向级别 ，7最高  
			curl_setopt($conn[$k], CURLOPT_HTTPHEADER, $header);//这里不要header，加块效率
			curl_setopt($conn[$k], CURLOPT_FOLLOWLOCATION, 1); // 302 redirect  
			curl_setopt($conn[$k], CURLOPT_RETURNTRANSFER,TRUE);//要求结果为字符串且输出到屏幕上            
			curl_setopt($conn[$k], CURLOPT_HTTPGET, true);  
			curl_setopt($conn[$k], CURLOPT_URL, $url);
			curl_setopt($conn[$k], CURLOPT_SSL_VERIFYPEER, false); //不验证证书
			curl_setopt($conn[$k], CURLOPT_SSL_VERIFYHOST, false); //不验证证书
			curl_setopt($conn[$k], CURLOPT_HTTPPROXYTUNNEL, true); //这个参数用于通过http代理来走其它协议，比如ftp协议，这时http协议完全变成tunnel（管道的意思）。

			curl_multi_add_handle ($mh,$conn[$k]);  
		}
		/*do{
			curl_multi_exec($mh, $active);
		} while ($active);*/
		/***节约内存优化***/
		do {
            $sMultiResource = curl_multi_exec($mh, $active);
            curl_multi_select($mh);
        } while ($active > 0 || $sMultiResource == CURLM_CALL_MULTI_PERFORM);
		
		foreach ($array as $k => $url) {  
			if(!curl_errno($conn[$k])){  
				yield $k => curl_multi_getcontent($conn[$k]);//数据转换为array  
				$header[$k] = curl_getinfo($conn[$k]);//返回http头信息  
				curl_close($conn[$k]);//关闭语柄  
				curl_multi_remove_handle($mh, $conn[$k]);   //释放资源   
			}else{  
				unset($k,$url);  
			}
		}
		curl_multi_close($mh);  
	}
	/***curl 并发请求
		array($k=>$url) 
		timeout 超时时间
		返回 $k=>返回内容
	***/
	public static function batchCurlHttpFor($array,$timeout='15'){
		$mh = curl_multi_init();//创建多个curl语柄 
		foreach($array as $k=>$url){    
			$ip = self::getRandIp(); //每次请求都切换ip防止被封
			$header = array( 
				'CLIENT-IP:'.$ip,
				'X-FORWARDED-FOR:'.$ip
			); 
			$conn[$k]=curl_init($url);//初始化  
			//curl_setopt($conn[$k], CURLOPT_TIMEOUT, $timeout);//设置超时时间  
			curl_setopt($conn[$k], CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');  
			curl_setopt($conn[$k], CURLOPT_MAXREDIRS, 7);//HTTp定向级别 ，7最高  
			curl_setopt($conn[$k], CURLOPT_HTTPHEADER, $header);//这里不要header，加块效率
			curl_setopt($conn[$k], CURLOPT_FOLLOWLOCATION, 1); // 302 redirect  
			curl_setopt($conn[$k], CURLOPT_RETURNTRANSFER,TRUE);//要求结果为字符串且输出到屏幕上            
			curl_setopt($conn[$k], CURLOPT_HTTPGET, true);  
			curl_setopt($conn[$k], CURLOPT_URL, $url);
			curl_setopt($conn[$k], CURLOPT_SSL_VERIFYPEER, false); //不验证证书
			curl_setopt($conn[$k], CURLOPT_SSL_VERIFYHOST, false); //不验证证书
			curl_setopt($conn[$k], CURLOPT_HTTPPROXYTUNNEL, true); //这个参数用于通过http代理来走其它协议，比如ftp协议，这时http协议完全变成tunnel（管道的意思）。

			curl_multi_add_handle ($mh,$conn[$k]);  
		}
		/*do{
			curl_multi_exec($mh, $active);
		} while ($active);*/
		/***节约内存优化***/
		do {
            $sMultiResource = curl_multi_exec($mh, $active);
            curl_multi_select($mh);
        } while ($active > 0 || $sMultiResource == CURLM_CALL_MULTI_PERFORM);
		
		foreach ($array as $k => $url) {  
			if(!curl_errno($conn[$k])){  
				$data[$k]=curl_multi_getcontent($conn[$k]);//数据转换为array  
				$header[$k] = curl_getinfo($conn[$k]);//返回http头信息  
				curl_close($conn[$k]);//关闭语柄  
				curl_multi_remove_handle($mh, $conn[$k]);   //释放资源   
			}else{  
				unset($k,$url);  
			}
		}
		curl_multi_close($mh);  
		return $data;
	}
	//返回随机国内ip
	private function getRandIp(){
		$arr_1 = array("218","218","66","66","218","218","60","60","202","204","66","66","66","59","61","60","222","221","66","59","60","60","66","218","218","62","63","64","66","66","122","211");
		$randarr= mt_rand(0,count($arr_1)-1);
		$ip1id = $arr_1[$randarr];
		$ip2id=  round(rand(600000,  2550000)  /  10000);
		$ip3id=  round(rand(600000,  2550000)  /  10000);
		$ip4id=  round(rand(600000,  2550000)  /  10000);
		return  $ip1id . "." . $ip2id . "." . $ip3id . "." . $ip4id;
	}
}