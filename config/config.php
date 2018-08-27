<?php
/***数据库配置***/
define('HOST','127.0.0.1');
define('USERNAME','root');
define('PASSWD','');
define('DBNAME','test');
define('FIX','');

/***rabbitmq配置***/
define('RABBITHOST','127.0.0.1');
define('RABBITPORT',5672);
define('RABBITUSERNAME','guest');
define('RABBITPASSWORD','guest');
define('RABBITVHOST','/');

/***rabbitmq队列***/
define('EXCHANGENAME','biquge');
define('QUEUEBOOKSLIST','queuebooks');
define('QUEUEBOOKSCHAPTER','queuebooksChapter');
define('ROUTINGKEY','biquge_send');
define('CHAPTERKEY','chapter_send');

/***网站目录配置***/
define('APP_PATH',str_replace('\\','/',realpath(dirname(__FILE__).'/../')));
define('SITE_PATH','');