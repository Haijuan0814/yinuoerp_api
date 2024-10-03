<?php

defined('BASEPATH') OR exit('No direct script access allowed');

define('SEASON', 'winter'); //夏令时summer,冬令时winter

class Home extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('weixinapi');
    }

    public function set_cache() {
        $this->cache->memcached->save('shayuanwei', 'shayuanwei#cache#' . time(), 3000);
    }

    public function get_cache() {
        var_dump($this->cache->memcached->get('shayuanwei'));
    }

    //主入口，每1分钟执行一次
    public function index() {
        //$this->message();
        //$this->clear();
        exit('crontab success ' . date('Y-m-d H:i:s', time()));
    }

    //发消息
    public function message() {
        $tablename = 'base_message';
        //$rows = $this->db->limit(1000)->where('is_send', 0)->where('set_time <', time())->get($tablename)->result();
        $rows = $this->db->limit(1000)->where('id', 134)->get($tablename)->result();
        foreach ($rows as $row) {
            //组织内容
            $description = $row->description;
            if ($row->from_uid) {
                $from_user = $this->user_model->row($row->from_uid);
                $description .= '<br><br><div class="gray">来自于' . $from_user->department_name . ' ' . $from_user->name . '</div>';
            }
            $url = "http://120.26.4.58:8123/#/base/message/land/". $row->id;
            $param = array(
                'touser' => $row->to_uid,
                'msgtype' => 'textcard',
                'agentid' => 0,
                'textcard' => array(
                    'title' => $row->title,
                    'description' => $description,
                    'url' => $url, 
                )
            );

            if (!$row->wxchat||true) {
                //从单点通道发（小秘书）
                $param = array_merge($param,array(
                        'agentid' => 1000002,
                        'wx_secret' => 'gATLJXKH4F1cN2TZ2eO67ZFI9TYPCepqyKIJEt6-row',
                    ));
                $ret = $this->weixinapi->send_message('/message/send', $param);
                var_dump($ret);
            } else {
                //从群聊通道发
                $wxchat = $this->db->where(array(
                            'key' => $row->wxchat
                        ))->get('base_wxchat')->row();
                $param = array_merge($param, array(
                    'chatid' => $wxchat->chatid
                ));
                $ret = $this->weixinapi->send('/appchat/send', $param);
            }
            $this->db->set(array(
                'is_send' => 1,
                'set_time' => time(),
                'wx_send_return' => json_encode($ret),
            ))->where('id', $row->id)->update($tablename);
        }
    }

    //自动清理系统数据
    public function clear() {
        $this->db->where(array('insert_time <' => strtotime('-3 month'),))->delete('base_message');
    }

}
