<?php

class Weixinapi {

    public $domain = 'https://qyapi.weixin.qq.com/cgi-bin';
    public $corpid = 'wwd3d8ad9ed5a8f390'; //企业微信通用ID
    public $secret_message= 'gATLJXKH4F1cN2TZ2eO67ZFI9TYPCepqyKIJEt6-row';  //小秘书通道
    public $secret='zULX0y_xYgNzP7eS_XmzFvtz-0iUNwuiGMFyPC0-78o';//通讯录


//默认唯一对外服务的功能
    public function send($path, $data = null, $method = 'post') {
        if($path=="/message/send"){
            $access_token = $this->access_token($this->secret_message);
        }else{
            $access_token = $this->access_token($this->secret);
        }
        $url = config_item('weixin_url') . $path . '?access_token=' . $access_token;
        $ret = ($method == 'get') ? request_curl($url . '&' . http_build_query($data)) : request_curl($url, $data);
        return json_decode($ret);
    }
    
    //默认唯一对外服务的功能

    public function send_message($path, $data = null, $method = null ,$webhook = null) {
        $access_token = $this->get_access_token($this->secret_message);
        $url = $this->domain . $path . '?access_token=' . $access_token . $webhook;
        if ($method == 'get') {
            return request_curl($url . '&' . http_build_query($data));
        } else {
            return request_curl($url, $data);
        }
    }

    //缓存 access_token
    public function get_access_token($secret = null) {
        $CI = & get_instance();
        $CI->load->driver('cache');
        if (!$secret)
            $secret = $this->secret;
        $cache_key = 'weixin::access_token::' . $secret;
        $access_token = $CI->cache->file->get($cache_key);
        if (!$access_token) { 
            $url = ($this->domain . '/gettoken?corpid=' . $this->corpid . '&corpsecret=' . $secret);
            $ret = request_curl($url);
            $json = json_decode($ret);
            $access_token = $json->access_token;
            $CI->cache->file->save($cache_key, $access_token, 3600);
        }
        return $access_token;
    }

    //缓存 jsapi_ticket
    public function get_jsapi_ticket() {
        $CI = & get_instance();
        $CI->load->driver('cache');
        $cache_key = 'weixin::jsapi_ticket::';
        $ticket = $CI->cache->file->get($cache_key);
        if (!$ticket) {
            $ret = json_decode($this->send("/get_jsapi_ticket"));
            $ticket = $ret->ticket;
            $CI->cache->file->save($cache_key, $ticket, 3600);
        }
        return $ticket;
    }

    public function get_jsapi() {
        $jsapi_ticket = $this->get_jsapi_ticket();
        $url = site_url($_SERVER['PATH_INFO']);
        $timestamp = time();
        $nonceStr = substr(md5(time()), 10, 16);
        $signstring = 'jsapi_ticket=' . $jsapi_ticket . '&noncestr=' . $nonceStr . '&timestamp=' . $timestamp . '&url=' . $url;
        $ret = array(
            'url' => $url,
            'appId' => $this->corpid,
            'timestamp' => $timestamp,
            'nonceStr' => $nonceStr,
            'signature' => sha1($signstring),
            'beta' => true,
        );
        return (object) $ret;
    }

    function access_token() {
        $json = json_decode($this->request($this->domain . '/gettoken?corpid=' . $this->corpid . '&corpsecret=' . $this->secret));
        $this->access_token = $json->access_token;
        return $this->access_token;
    }

    public function upload_media_csv($filename) {
        if (version_compare(phpversion(), '5.4.0') >= 0) {
            $data = array('media' => new CURLFile($filename));
        } else {
            $data = array('media' => '@' . $filename);
        }
        //new CURLFile(realpath('image.png')
        $url = "https://qyapi.weixin.qq.com/cgi-bin/media/upload?access_token={$this->access_token}&type=file";
        return $this->request_post($url, $data);
    }

    public function replace_user($media_id) {
        $url = "https://qyapi.weixin.qq.com/cgi-bin/batch/replaceuser?access_token=" . $this->access_token;
        $data = array("media_id" => $media_id);
        $result = $this->request_post($url, json_encode($data));
        return $result;
    }

    public function replace_department($media_id) {
        $url = "https://qyapi.weixin.qq.com/cgi-bin/batch/replaceparty?access_token=" . $this->access_token;
        $data = array("media_id" => $media_id, "callback" => array("url" => "http://api.yilin.me/index.php/base/crontab/sync_weixin_user"));
        $result = $this->request_post($url, json_encode($data));
        return $result;
    }

    function request($url, $data = null) {
        return file_get_contents($url, false, stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $data
            )
        )));
    }

    function request_post($url, $data) {
        //初始化cURL方法
        $ch = curl_init();
        //设置cURL参数
        $opts = array(
            //在局域网内访问https站点时需要设置以下两项，关闭ssl验证！
            //此两项正式上线时需要更改（不检查和验证认证）
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data
        );
        curl_setopt_array($ch, $opts);
        //执行cURL操作
        $output = curl_exec($ch);
        if (curl_errno($ch)) {    //cURL操作发生错误处理。
            var_dump(curl_error($ch));
            die;
        }
        //关闭cURL
        curl_close($ch);
        $res = json_decode($output);
        return($res);   //返回json数据
    }

}
