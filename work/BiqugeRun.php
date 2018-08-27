<?php
class BiqugeRun extends Thread{
	
	private $URL = 'https://www.biquge.info/wanjiexiaoshuo/';
	private $HOSTURL = 'https://www.biquge.info/';
	private $BEGIN_NUM = 21;
	private $NUMDATA = 26;
	private $MAXNUM = 200; 
	
	public function run(){
		write_log("目录抓取子线程开始运行");
		for($this->BEGIN_NUM;$this->BEGIN_NUM<$this->NUMDATA;$this->BEGIN_NUM++){
			
			$url = $this->URL.$this->BEGIN_NUM;
			$chG = new BiqugeBook(null,'getBooksList',$url,'');
			if($chG ->start()){
				$chG->join();
				
				$gl = $chG->response;
				if($gl){
					$gl->kill();
				}
				/*echo $this->BEGIN_NUM;
				exit();
				
				$this->NUMDATA = $chG->NUMDATA();
				for($this->BEGIN_NUM < $this->NUMDATA){
					$this->BEGIN_NUM++;
					$url = $this->URL.$this->BEGIN_NUM;
				}*/
			}else{
				echo '线程启动失败';
			}
		}
	}
	/***书***/
	
	/***获取文章和章节信息***/
	public function getBooksInfo($envelope, $queue){
				/*$queue->ack($envelope->getDeliveryTag());
				return;*/
				
		write_log("Book队列开始监控");
		$chG = new BiqugeBook($envelope->getBody(),'getBooksInfo','');

		if($chG->start()){
			$chG->join();
			if($chG->getEnd()){
				$queue->ack($envelope->getDeliveryTag());
			}
			$gl = $chG->response;
			if($gl){
				$gl->kill();
			}
		}else{
			echo '线程启动失败';
			exit();
		}
		unset($envelope);
		unset($queue);
		unset($chG);
		unset($gl);
	}
	/***章节***/
	public function getChapterInfo($envelope, $queue){
		
		/*$queue->ack($envelope->getDeliveryTag());
		return;*/
		write_log("章节队列监控开始运行");
		$chG = new BiqugeBook($envelope->getBody(),'getChapterInfo','','');
		
		if($chG ->start()){
			$chG->join();
			if($chG->getEnd()){
				$queue->ack($envelope->getDeliveryTag());
			}
			$gl = $chG->response;
			if($gl){
				$gl->kill();
			}
		}else{
			echo '线程启动失败';
		}
		unset($envelope);
		unset($queue);
		unset($chG);
		unset($gl);
	}
}
class BiqugeBook extends Thread {
	private $msg = null;
	private $db = null;
	private $fcname = null;
	private $isEnd = false;
	private $MAXNUM = 200; //并发curl数
	private $URL = null;
	private $NUMDATA = null;
	private $ARR = null;
	private $TDS = null;
	private $TDSNUM = 4; // 并发线程数
	public function __construct($msg,$fcname,$url,$arr) {
		$this->msg = $msg;
		$this->fcname = $fcname;
		$this->URL = $url;
		$this->ARR = $arr;
	}
	
	public function run(){
		/*global $CONFIG;
		var_dump($CONFIG);
		write_log("线程传值:");
		return;*/
		//$biquge = new Biquge();
		switch($this->fcname){
			case 'getChapterInfo':
				$this->getChapterInfo($this->msg);
				break;
			case 'getBooksList':
				$this->getBooksList($this->URL);
				break;
			case 'getBooksInfo':
				$this->getBooksInfo($this->msg);
				break;
			case 'getCurlChapterContent':
				$this->getCurlChapterContent($this->ARR);
				break;
			default:
				write_log('方法不存在');
				break;
		}
		
	}
	
	/***获取文章和章节信息***/
	public function getBooksInfo($msg){
		
		if($msg != null && $msg != ''){
			$msg = json_decode($msg);
			foreach($msg as $k=>$v){
				$arr[$k] = $v->source_url;
			}
			/***获取rabbmit bookList 数据***/
			$data = Curl::batchCurlHttp($arr);
			$insertData = array();
			
			$this->isEnd = true;
			foreach($data as $k=>$content){
				phpQuery::newDocumentHTML($content);
				
				$cover_link = pq('#fmimg')->find('img')->attr("src");
				
				MysqlDB::getInstance()->where("name", $msg[$k]->title);
				$novel = MysqlDB::getInstance()->getOne("novel");
				write_log("Book信息抓取");
				if($novel){
					continue;
				}
				MysqlDB::getInstance()->where("name", $msg[$k]->author);
				$author = MysqlDB::getInstance()->getOne("author");
				if($author){
					$authorid = $author["id"];
				}else{
					$authorid = MysqlDB::getInstance()->insert ('author', array("name"=>$msg[$k]->author));
				}
				if(!$authorid){
					write_log($msg[$k]->author."插入失败");
					continue;
				}
				$chaptersInArr = array();
				$chapters = pq('#list')->find('a');
				$dataIn =array(
					'name'=>$msg[$k]->title,
					'description'=>strIosToUtf8(pq('#intro')->find('p')->eq(0)->text()),
					'author_id'=>$authorid,
					'type'=>category_maps($msg[$k]->type),
					'source'=>'biquge',
					'source_link'=>$msg[$k]->source_url,
					'chapter_num'=>$chapters->length,
					'is_over'=>$msg[$k]->states == '完成' ? 1:0,
					'created_at'=>date('Y-m-d H:i:s',time()),
					'updated_at'=>date('Y-m-d H:i:s',time()),
				);
				
				$nid = MysqlDB::getInstance()->insert ('novel', $dataIn);

				if(!$nid){
					write_log($dataIn['name']."插入失败");
					continue;
				}else{
					write_log($dataIn['name']."插入成功");
				}
				/***图片下载***/
				
				$cover_ext = substr($cover_link, strrpos($cover_link, '.') + 1);
				$path = APP_PATH.SITE_PATH.'/public/cover/' . $nid . '_cover.' . $cover_ext;
				
				write_log($path );
				//文件不存在时才获取图片
				if (!file_exists($path)) {
					$cover = file_get_contents($cover_link);
					file_put_contents($path, $cover);
					MysqlDB::getInstance()->where ('id', $nid);
					MysqlDB::getInstance()->update('novel',array('cover'=>'/cover/' . $nid . '_cover.' . $cover_ext));
				}
				
				/***小说进入章节抓取队列***/
				foreach($chapters as $dk=>$a){
					$inData = array();
					$inData['novel_id'] = $nid;
					$inData['name'] = strIosToUtf8(pq($a)->text());
					$inData['source_link'] = $msg[$k]->source_url.pq($a)->attr('href');
					array_push($chaptersInArr,$inData);
				}
				$ra = new RabbitMQCommand(EXCHANGENAME,QUEUEBOOKSCHAPTER,CHAPTERKEY);
				$ra->send($chaptersInArr);
				write_log("unset内存回收前:".memory_get_usage());
				gc_collect_cycles();
				unset($cover_link);
				unset($dataIn);
				unset($chapters);
				unset($chaptersInArr);
				unset($inData);
				unset($cptArr);
				unset($chapterArr);
				unset($cover);
				unset($path);
				unset($content);
				write_log("unset内存回收后:".memory_get_usage());
				
				/***章节内容进入队列***/
			}
		}else{
			write_log("空队列");
		}
	}
	/***获取BOOK***/
	public function getBooksList($url){
		
		write_log('正在抓取小说列表'.$url);
		$data = array();
		$content = Curl::httpGet($url);
		if($content == ""){
			write_log('没有抓取到网页'.$url);
			return ;
		}
		phpQuery::newDocumentHTML($content);
		$books  = pq('.novelslistss')->eq(0)->find('li');
		foreach($books as $book){
			$arr = array();
			$arr['type'] = strIosToUtf8(pq($book)->find('.s1')->text());
			$arr['title'] = strIosToUtf8(pq($book)->find('.s2')->find('a')->text());
			$arr['source_url'] = pq($book)->find('.s2')->find('a')->attr('href');
			$arr['author'] = strIosToUtf8(pq($book)->find('.s4')->text());
			$arr['states'] = strIosToUtf8(pq($book)->find('.s7')->text());
			$arr['type'] = str_replace('[','',$arr['type']); 
			$arr['type'] = str_replace(']','',$arr['type']); 
			$arr['type'] = mb_ereg_replace('小说','',$arr['type']);
			array_push($data,$arr);
			$arr = null;
		}
		if(!$this->NUMDATA){
			$this->NUMDATA = pq("#pagelink")->find(".last")->text();
		}
		$content = null;
		/***进去rabbitmq队列***/
		//$this -> getBooksInfo($data);
		write_log('抓取完成小说列表'.$url);
		$ra = new RabbitMQCommand(EXCHANGENAME,QUEUEBOOKSLIST,ROUTINGKEY);
		$ra->send($data);
		unset($data);
		return true;
	}
	/***获取章节信息***/
	private function getChapterInfo($msg){
		write_log("开始循环");
		/***获取rabbmitmq 章节内容***/
		$arr = array();
		$chaptersInArr = array();
		if($msg != null && $msg != ''){
			$msg = json_decode($msg);
			$arr = array();
			$df = null;
			write_log('msg数量：'.count($msg));
			foreach($msg as $k=>$v){
				$arr[$k] = $v->source_link;
				if(count($arr) == $this->MAXNUM){
					if(count($df) <= $this->TDSNUM){
						$df[] = new BiqugeChapter('','getCurlChapterContent','',$arr);
					}
					if(count($df) == $this->TDSNUM){
						foreach($df as $thread) {
							$thread->start();
						}
						 
						foreach($df as $thread) {
							$thread->join();
						}
						
						foreach($df as $thread) {
							$cptdata = $thread->getINDATA();
							foreach($cptdata as $ck=>$cv){
								$cptdata[$ck]['novel_id'] = $msg[$ck]->novel_id;
								$cptdata[$ck]['name'] = $msg[$ck]->name;
								$cptdata[$ck]['source_link'] = $msg[$ck]->source_link;;
								$cptdata[$ck]['views'] = 0;
								$cptdata[$ck]['created_at'] = date('Y-m-d H:i:s',time());
								$cptdata[$ck]['updated_at'] = date('Y-m-d H:i:s',time());
							}
							$chaptersInArr = array_merge($chaptersInArr,$cptdata);
							$gl = $thread->response;
							if($gl){
								$gl->kill();
							}
						}
						unset($df);
						$df == null;
					}
					unset($arr);
					$arr = array();
				}
			}
			if($arr){
				$df[] = new BiqugeChapter('','getCurlChapterContent','',$arr);
				foreach($df as $thread) {
					$thread->start();
				}
				 
				foreach($df as $thread) {
					$thread->join();
				}
				foreach($df as $thread) {
					$cptdata = $thread->getINDATA();
					foreach($cptdata as $ck=>$cv){
						$cptdata[$ck]['novel_id'] = $msg[$ck]->novel_id;
						$cptdata[$ck]['name'] = $msg[$ck]->name;
						$cptdata[$ck]['source_link'] = $msg[$ck]->source_link;
						$cptdata[$ck]['views'] = 0;
						$cptdata[$ck]['created_at'] = date('Y-m-d H:i:s',time());
						$cptdata[$ck]['updated_at'] = date('Y-m-d H:i:s',time());
					}
					$chaptersInArr = array_merge($chaptersInArr,$cptdata);
					$gl = $thread->response;
					if($gl){
						$gl->kill();
					}
				}
				$df == null;
			}
			/*$inDataArr = $this->getCurlChapterContent($msg);
			foreach($inDataArr as $inData){
				array_push($chaptersInArr,$inData);
				unset($inData);
			}*/
			
			write_log("开始插入数据库");
			$ids = MysqlDB::getInstance()->insertMulti('chapter', $chaptersInArr);
			
			if(!$ids){
				write_log($msg[1]->novel_id."插入失败");
				echo 'update failed: ' . MysqlDB::getInstance()->getLastError();
				exit();
			}else{
				write_log($msg[1]->novel_id."插入成功，共".count($msg)."条");
				$this->isEnd = true;
			}
			gc_collect_cycles();
			unset($chaptersInArr);
			unset($msg);
			unset($ids);
			unset($envelope);
			unset($queue);
			write_log("子线程执行结束");
		}
	}
	/***返回抓取是否成功***/
	public function getEnd(){
		return $this->isEnd; 
	}
	/***返回页数***/
	public function getNUMDATA(){
		return $this->NUMDATA; 
	}
	function yieldForArr($arr){
		foreach($arr as $k=>$v){
			yield $k=>$v;
		}
	}
}

class BiqugeChapter extends Thread {
	private $msg = null;
	private $db = null;
	private $fcname = null;
	private $isEnd = false;
	private $MAXNUM = 500;
	private $URL = null;
	private $NUMDATA = null;
	private $ARR = null;
	private $TDS = null;
	private $INDATA = array();
	
	public function __construct($msg,$fcname,$url,$arr) {
		$this->msg = $msg;
		$this->fcname = $fcname;
		$this->URL = $url;
		$this->ARR = $arr;
	}
	public function run(){
		switch($this->fcname){
			case 'getCurlChapterContent':
				$this->getCurlChapterContent($this->ARR);
				break;
			default:
				write_log('方法不存在');
				break;
		}
		
	}
	
	/***抓取章节***/
	private function getCurlChapterContent($arr){
		$chapterArr = Curl::batchCurlHttp($arr);
		$inDataArr = array();
		foreach($chapterArr as $ck=>$cval){
			phpQuery::newDocumentHTML($cval);
			$inDatat = array();
			$inDatat['content'] = pq("#content")->html();
			//yield $ck=>$inData;
			$inDataArr[$ck] = $inDatat;
			//[$ck] = $inDatat;
			
			unset($cval);
			//unset($inData);
		}
		$this->INDATA = $inDataArr;
		unset($chapterArr);
		unset($arr);
	}
	/***子线程抓取章节**
	private function getTDChapterContent($msg){
		$msgy = $this->yieldForArr($msg);
		$arr = array();
		foreach($msgy as $k=>$v){
			$arr[$k] = $v->source_link;
			if(count($arr) == $this->MAXNUM){
				write_log($v->novel_id."抓取章节");
				$chapterArr = Curl::batchCurlHttp($arr);
				foreach($chapterArr as $ck=>$cval){
					phpQuery::newDocumentHTML($cval);
					$inData = array();
					$inData['novel_id'] = $msg[$ck]->novel_id;
					$inData['name'] = $msg[$ck]->name;
					$inData['content'] = pq("#content")->html();
					$inData['source_link'] = $msg[$ck]->source_link;;
					$inData['views'] = 0;
					$inData['created_at'] = date('Y-m-d H:i:s',time());
					$inData['updated_at'] = date('Y-m-d H:i:s',time());
					yield $ck=>$inData;
					
					unset($cval);
					unset($inData);
				}
				unset($chapterArr);
				unset($arr);
				$arr = array();
			}
		}
		
		if($arr != null){
			$chapterArr = Curl::batchCurlHttp($arr);
			foreach($chapterArr as $ck=>$cval){
				phpQuery::newDocumentHTML($cval);
				$inData = array();
				$inData['novel_id'] = $msg[$ck]->novel_id;
				$inData['name'] = $msg[$ck]->name;
				$inData['content'] = pq("#content")->html();
				$inData['source_link'] = $msg[$ck]->source_link;;
				$inData['views'] = 0;
				$inData['created_at'] = date('Y-m-d H:i:s',time());
				$inData['updated_at'] = date('Y-m-d H:i:s',time());
				yield $ck=>$inData;
				unset($cval);
				unset($inData);
			}
			unset($chapterArr);
			unset($arr);
		}
	}*/
	/***返回抓取章节***/
	public function getINDATA(){
		write_log("抓到".count($this->INDATA)."章内容");
		return $this->INDATA;
	}
}