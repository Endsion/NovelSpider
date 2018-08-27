<?php
set_time_limit(0);
date_default_timezone_set('PRC');
require_once('config/config.php');  //配置文件
require_once('function/autoload.php'); //功能文件
require_once('work/autoload.php'); //业务文件
header("Content-type: text/html; charset=utf-8");

class App{
	public function run(){
		write_log("主线程开始运行");
		//$threads[] = new BiqugeRun(); //根据分页规则抓取book
		//$threads[] = new MQMonitor(EXCHANGENAME,QUEUEBOOKSLIST,ROUTINGKEY,'BiqugeRun','getBooksInfo'); //监控book队列、抓取book信息和章节列表
		//$threads[] = new MQMonitor(EXCHANGENAME,QUEUEBOOKSCHAPTER,CHAPTERKEY,'BiqugeRun','getChapterInfo'); //监控chapter队列、抓取章节信息
		
		foreach($threads as $thread) {
			$thread->start();
		}
		 
		foreach($threads as $thread) {
			$thread->join();
		}
		echo '<br>运行结束';
	}
}
$app = new App();
$app->run();