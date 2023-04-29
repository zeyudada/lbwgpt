<?php
/*
 * @Author: zeyudada
 * @Date: 2022-10-28 21:09:20
 * @LastEditTime: 2023-04-29 15:28:41
 * @Description: ChatGPT
 * @Q Q: zeyunb@vip.qq.com(1776299529)
 * @E-mail: admin@zeyudada.cn
 * 
 * Copyright (c) 2022 by zeyudada, All Rights Reserved.
 */

session_start();

error_reporting(E_ERROR | E_PARSE);
ini_set("max_execution_time", "60");

require_once('./db.class.php');
$DB = new DB('localhost', 'novelai', 'j2hBhTRmJfAGbtsy', 'novelai', 3306);

$act = isset($_GET['act']) ? $_GET['act'] : null;
if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
} elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = trim(end(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}
$ipmd5 = substr(md5($ip), 8, 16);

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

require_once 'vendor/autoload.php';



switch ($act) {
    case 'openai':
        if (empty($_POST['content'])) json(['code' => 201, 'msg' => '缺少参数或参数不合法']);
        if ($redis->exists($ip)) {
            $count = $redis->get($ip);
            if ($count > 5) json(['code' => 203, 'msg' => '限制每分钟生成5次！']);
        } else {
            $redis->incr($ip);
            $redis->expire($ip, 60);
        }
        $asking = $ipmd5 . 'status';
        if ($redis->exists($asking)) json(['code' => 202, 'msg' => '请勿重复提交！']);
        $redis->set($asking, 'asking');
        $redis->expire($asking, 30); // 防止PHP意外终止导致锁死
        $key = [
            "sk-xxxxx",
        ];
    
        if ($redis->exists('openai')) {
            $count = $redis->get('openai');
            $redis->incr('openai');
        } else {
            $redis->incr('openai');
            $count = 0;
        }
        $node = $count % count($key);
        $header = ['Content-Type: application/json', 'Authorization: Bearer ' . $key[$node]];

        $hint = empty($_POST['hint']) ? "对话中严禁涉及色情 有关政治和中国法律禁止的内容" : $DB->escape($_POST['hint']);
        $total = 0;
        $total += strlen($hint);
        $history = $DB->getAll("SELECT * FROM `chat` WHERE `user` = '$ipmd5'");
        $messages = [["role" => "system", "content" => $hint]];
        foreach ($history as $value) {
            $messages[] = ["role" => "user", "content" => $value['content']];
            $total += strlen($value['content']);
        }
        
        $total += strlen($_POST['content']);
        if ($total > 4000) $DB->query("DELETE FROM `chat` WHERE `user` = '$ipmd5'");
        $messages[] = ["role" => "user", "content" => $_POST['content']];
        $data = [
            "model" => "gpt-3.5-turbo",
            "messages" => $messages,
            "max_tokens" => 4096 - $total,
            "temperature" => 0.6,
            "top_p" => 1,
            // "stop" => ["\r\n", "\n"],
            "user" => $ipmd5
        ];
    
        $res = curlpost('https://api.openai.com/v1/completions', $data, $header);
        $json = json_decode($res[0], true);
        $redis->del($asking);
        if($json['choices'][0]['message']['content']){
            $DB->insert_array('chat', [
                'ip' => $ip,
                'content' => $DB->escape($_POST['content']),
                'reply' => $DB->escape($json['choices'][0]['message']['content']),
                'user' => $ipmd5,
                'time' => date('Y-m-d H:i:s')
            ]);
            json(['code' => 200, 'msg' => '解析成功！', 'text' => Parsedown::instance()->text($json['choices'][0]['message']['content']), 'data' => $json]);
        }
        json(['code' => 500, 'msg' => '获取失败', 'data' => $json]);
        break;

    case 'historyopenai':
        # 获取历史记录
        $history = $DB->getAll("SELECT * FROM `chat` WHERE `user` = '$ipmd5'");
        $messages = [];
        foreach ($history as $value) {
            $messages[] = ["messageType" => "text", "headIcon" => "/assets/images/user.png", "name" => "您", "position" => "right", "html" => $value['content']];
            $messages[] = ["messageType" => "raw", "headIcon" => "/assets/images/chatgpt.png", "name" => "ChatGPT", "position" => "left", "html" => $value['reply']];
        }
        json(['code' => 200, 'msg' => '获取成功', 'data' => $messages]);
        break;

    case 'clearopenai':
        # 清除历史记录
        $DB->query("DELETE FROM `chat` WHERE `user` = '$ipmd5'");
        json(['code' => 200, 'msg' => '清除成功！']);
        break;


    default:
        # 啥也不是
        json(['code' => 500, 'msg' => '不存在的方法！']);
        break;
}


function curlpost($url, $data, $header = ['accept: application/json', 'Content-Type: application/json'])
{
    $data_string = json_encode($data, JSON_UNESCAPED_UNICODE);
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1800);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    return [$response, $info];
}

/**
 * @description: 用于输出json结果
 * @param {array} $json 输入的数组
 */
function json($json = [])
{
    @header('Content-Type: application/json');
    exit(json_encode($json)); //传参
}
