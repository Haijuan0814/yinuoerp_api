<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Message extends MY_Controller {
    
    public $tablename = 'base_message';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    /*
    Anthor：shihaijuan
    Date：2021-12-22
    Desc：消息列表
    */
    public function index() {
        $pageSize = 10; 
        $current = $this->input->get("current") ? $this->input->get("current") : 0;
        $this->db->where('to_uid',$this->user->id)->where('is_read',0)->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($pageSize, $current * $pageSize)->get()->result();
        foreach ($rows as $row) {
            if ($row->from_uid) $row->from_user = cache_user($row->from_uid);
        }
        json_success(array(
            "list" => $rows,
            "total" => $total,
            'current' => $current,
            'pageSize' => $pageSize,
        ));
    }
    
    /*
    Anthor：shihaijuan
    Date：2021-12-22
    Desc：消息跳转
    */
    public function go() {
        $url = '';
        $id = $this->input->get('id');
        $row = $this->db->where(array('id' => $id))->get($this->tablename)->row();
        if (!$row) json_fail('message deleted');
        $route = $this->db->where(array('table_name' => $row->module))->get('base_menu')->row();
        if (!$route) json_fail ('找不到匹配的路由url');
        $url = $route->url_message;
        //多参数和单参数
        if (strstr($row->param,'{')){            
            foreach (json_decode($row->param) as $key => $value) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
        }else{
            $url = str_replace('{q}',$row->param,$url);
        }        
        if ($row->is_read == 0) $this->db->set(array('is_read' => 1))->where('id', $row->id)->where('to_uid',$this->user->id)->update($this->tablename);
        json_success(array(
            "url" => $url,
        ));
    }
    
    public function total() {
        $this->db->where('to_uid',$this->user->id)->where('is_read',0);
        $total = $this->db->count_all_results($this->tablename);
        json_success(array(
            "total" => $total,
        ));
    }
    

    //清空消息
    public function clear() {
        $this->db->set('is_read',1)->where('to_uid',$this->user->id)->where('is_read',0)->update($this->tablename);
        json_success(lang('success'));
    }
}