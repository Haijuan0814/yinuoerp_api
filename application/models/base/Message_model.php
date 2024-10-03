<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Message_model extends CI_Model {

    public $tablename = 'base_message';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
         $this->load->library('weixinapi');
    }

    public function insert($param) {
        $from_uid=$this->user?$this->user->id:0;
        $id=0;
        if($param["to_uid"]!=$from_uid||true){
            $where=array(
                'to_uid' => $param["to_uid"],
                'module' => $param["module"],
                'param' => $param["param"],//isset($param["param"]) ? json_encode($param["param"]) : "",
                'title' => $param["title"],
                'description' => isset($param["description"]) ? $param["description"] : "",
                'insert_time > ' => strtotime('-1 day'),
            );
            if (!$this->db->where($where)->count_all_results($this->tablename)||true) {
                //添加一条消息记录
                $this->db->set($param)->set(array(
                    'from_uid' => $this->user?$this->user->id:0,
                    'insert_time ' => time(),
                ))->insert($this->tablename);
                $id= $this->db->insert_id();
                //立刻通过企业微信进行发送 By-SHIHAIJUAN
                if($id){
                    $description = $where['description'];
                    $from_uid=$this->user?$this->user->id:0;
                    $url = "http://120.26.4.58:8123/#/base/message/land/". $id;
                    if ($from_uid) {
                        $from_user = $this->user_model->row($from_uid);
                        $description .= '<br><br><div class="gray">来自于' . $from_user->department_name . ' ' . $from_user->name . '</div>';
                    }
                    $_param = array(
                        'touser' => $where['to_uid'],
                        'msgtype' => 'textcard',
                        'agentid' => 0,
                        'textcard' => array(
                            'title' => $where['title'],
                            'description' => $description,
                            'url' => $url, 
                        )
                    );
                    
                    $_param = array_merge($_param,array(
                        'agentid' => 1000002,
                        'wx_secret' => 'gATLJXKH4F1cN2TZ2eO67ZFI9TYPCepqyKIJEt6-row',
                    ));
                    $ret = $this->weixinapi->send_message('/message/send', $_param);
                    $this->db->set(array(
                        'is_send' => 1,
                        'set_time' => time(),
                        'wx_send_return' => json_encode($ret),
                    ))->where('id', $id)->update($this->tablename);
                    //var_dump($ret);
                }
                    
            }
            
        }
        return $id;
        
    }
}
