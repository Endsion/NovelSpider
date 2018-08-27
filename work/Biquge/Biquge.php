<?php
class Biquge{
	private $URL = 'https://www.biquge.info/wanjiexiaoshuo/';
	private $HOSTURL = 'https://www.biquge.info/';
	private $BEGIN_NUM = 5;
	private $NUMDATA = null;
	private $MAXNUM = 500; 
	function __construct(){
		
    }
	public function run(){
		$url = $this->URL.$this->BEGIN_NUM;
		$arr = $this->getBooksList($url);
			echo $this->BEGIN_NUM;
			exit();
		if($this->BEGIN_NUM < $this->NUMDATA){
			$this->BEGIN_NUM++;
			$url = $this->URL.$this->BEGIN_NUM;
			$arr = $this->getBooksList($url);
		}
	}
	
	/***获取文章***/
	public function getBooksList($url){
		global $CONFIG;
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
		
		//$ra = new RabbitMQCommand($CONFIG['Biquge']['exchangeName'],$CONFIG['Biquge']['queueBooksChapter'],$CONFIG['Biquge']['chapterKey']);
		$ra = new RabbitMQCommand($CONFIG['Biquge']['exchangeName'],$CONFIG['Biquge']['queueBooksList'],$CONFIG['Biquge']['routingKey']);
		$ra->send($data);
		unset($data);
		return true;
	}
	/***获取文章和章节信息***/
	public function getBooksInfo($envelope, $queue){
		$msg = $envelope->getBody();
		$arr = array();
		if($msg != null && $msg != ''){
			$msg = json_decode($msg);
			foreach($msg as $k=>$v){
				$arr[$k] = $v->source_url;
			}
		}
		/***获取rabbmit bookList 数据***/
		$data = Curl::batchCurlHttp($arr);
		$insertData = array();
		foreach($data as $k=>$content){
			phpQuery::newDocumentHTML($content);
			
			$cover_link = pq('#fmimg')->find('img')->attr("src");
			
			MysqlDB::getInstance()->where("name", $msg[$k]->title);
			$novel = MysqlDB::getInstance()->getOne("novel");
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
			var_dump($dataIn);
			exit();
			$nid = MysqlDB::getInstance()->insert ('novel', $dataIn);
			
			if(!$nid){
				write_log($dataIn['name']."插入失败");
				continue;
			}else{
				write_log($dataIn['name']."插入成功");
			}
			/***图片下载***/
			
			$cover_ext = substr($cover_link, strrpos($cover_link, '.') + 1);
			$path = $_SERVER['DOCUMENT_ROOT'].$CONFIG['AppPath'].'/public/cover/' . $nid . '_cover.' . $cover_ext;
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
			$ra = new RabbitMQCommand($CONFIG['Biquge']['exchangeName'],$CONFIG['Biquge']['queueBooksChapter'],$CONFIG['Biquge']['chapterKey']);
			$ra->send($chaptersInArr);
			/*$cptArr = array();
			foreach($chapters as $dk=>$a){
				$inData = array();
				$inData['novel_id'] = $nid;
				$inData['name'] = strIosToUtf8(pq($a)->text());
				$inData['content'] = '';
				$inData['source_link'] = $msg[$k]->source_url.pq($a)->attr('href');
				$inData['views'] = 0;
				$inData['created_at'] = date('Y-m-d H:i:s',time());
				$inData['updated_at'] = date('Y-m-d H:i:s',time());
				array_push($chaptersInArr,$inData);
				$cptArr[$dk]=$msg[$k]->source_url.pq($a)->attr('href');
				if(count($cptArr) == $this->MAXNUM){
					write_log($msg[$k]->title."抓取章节");
					$chapterArr = Curl::batchCurlHttp($cptArr);
					foreach($chapterArr as $ck=>$cval){
						phpQuery::newDocumentHTML($cval);
						$chaptersInArr[$ck]['content'] = pq("#content")->html();
						unset($cval);
					}
					unset($chapterArr);
					unset($cptArr);
					$cptArr = array();
				}
				unset($inData);
			}
			if($cptArr != null){
				$chapterArr = Curl::batchCurlHttp($cptArr);
				foreach($chapterArr as $ck=>$cval){
					phpQuery::newDocumentHTML($cval);
					$chaptersInArr[$ck]['content'] = pq("#content")->html();
					unset($cval);
				}
			}
			$ids = MysqlDB::getInstance()->insertMulti('chapter', $chaptersInArr);
			
			if(!$ids){
				write_log($msg[$k]->source_url."插入失败");
				echo 'update failed: ' . $MysqlDB::getInstance()->getLastError();
				exit();
			}*/
			write_log("unset内存回收前:".memory_get_usage());
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
			write_log("gc_collect_cycles内存回收前:".memory_get_usage());
			gc_collect_cycles();
			write_log("gc_collect_cycles内存回收后:".memory_get_usage());
			
			/***章节内容进入队列***/
		}
		exit();
		$queue->ack($envelope->getDeliveryTag());
		
	}
	
	/***获取章节信息***/
	public function getChapterInfo($envelope, $queue){
		$queue->ack($envelope->getDeliveryTag());
		exit();
		include_once __DIR__.'/../../load.php';
		
		write_log("yield内存回收前:".memory_get_usage());
		$msg = $envelope->getBody();
		/***获取rabbmitmq 章节内容***/
		$arr = array();
		$chaptersInArr = array();
		if($msg != null && $msg != ''){
			$msg = json_decode($msg);
			$inDataArr = $this->getCurlChapterContent($msg);
			foreach($inDataArr as $inData){
				array_push($chaptersInArr,$inData);
				unset($inData);
			}
		}
		$ids = MysqlDB::getInstance()->insertMulti('chapter', $chaptersInArr);
		
		if(!$ids){
			write_log($msg[1]->novel_id."插入失败");
			echo 'update failed: ' . $MysqlDB::getInstance()->getLastError();
			exit();
		}else{
			write_log($msg[1]->novel_id."插入成功，共".count($msg)."条");
		}
		gc_collect_cycles();
		unset($chaptersInArr);
		unset($msg);
		unset($ids);
		$queue->ack($envelope->getDeliveryTag());
		unset($envelope);
		unset($queue);
		write_log("yield内存回收后:".memory_get_usage());
	}
	/******/
	private function getCurlChapterContent($msg){
		$msgy = $this->yieldForArr($msg);
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
				//$msg[$ck]->content = pq("#content")->html();
				unset($cval);
				unset($inData);
			}
			unset($chapterArr);
			unset($arr);
		}
	}
	function yieldForArr($arr){
		foreach($arr as $k=>$v){
			yield $k=>$v;
		}
	}
	/***下载封面***/
	public function getBooksCover(){
		/***获取rabbmitmq 章节内容***/
		$arr = array();
		
	}
	/***笔趣阁连载更新***/
	public function updateBooks(){
		/***获取rabbmitmq 连载book内容***/
		$arr = array();
	}
}