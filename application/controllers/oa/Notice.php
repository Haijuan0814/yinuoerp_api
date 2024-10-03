<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Notice extends MY_Controller {

    public $tablename = 'oa_notice';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }

    /*
    Anthor：shihaijuan
    Date：2021-10-22
    Desc：列表，分页
    */
    public function index() {
        $current = $this->input->get("current") ? $this->input->get("current") : 1;
        $pageSize = $this->input->get("pageSize") ? $this->input->get("pageSize") : config_item('page_per'); 
        $this->db->where('is_delete',0);  
        if ($this->input->get("keywords")){
            $this->db->like('title', $this->input->get("keywords"));
        }
        $this->db->select('id,title,insert_user,start_date,end_date,insert_time,content')->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($pageSize, ($current-1) * $pageSize)->get()->result();
        foreach ($rows as $row) {
            //$row = $this->detail_extend($row);
        }
        json_success(array(
            "list" => $rows,
            "total" => $total,
            'current' => $current,
            'pageSize' => $pageSize,
        ));
    }

    public function detail() {
        $row = $this->db->where(array(
            //'is_delete' => 0,
            'id' => $this->input->get('id')
        ))->get($this->tablename)->row();
        //$row = $this->detail_extend($row);
        $row ? json_success(array('row'=>$row)):  json_fail(lang('undefined'));
    }
    
    public function detail_extend($row) {
        if (!$row) return ;
        $row->insert_user = cache_user($row->uid);
        $row->comment = cache_comment($this->tablename, $row->id);
        $row->push_permission = $this->push_permission();
        return $row;
    }


     /*
    Anthor：shihaijuan
    Date：2021-11-26
    Desc：增删改
    */
    public function form(){
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        $method=$_SERVER['REQUEST_METHOD'];
        if($method=='POST'){
            $data = array();
            foreach (array('title','content','is_send') as $field) {
                $data[$field] = $params[$field];
            }
            foreach (array('start_date','end_date') as $field) {
                $data[$field] = isset($params[$field])&&$params[$field]?strtotime($params[$field]):0;
            }

            if (isset($params[$this->tablekey])&&$params[$this->tablekey]) {
                //$row = $this->db->where($this->tablekey,$params[$this->tablekey])->get($this->tablename)->row();
                //$this->permission($row);
                $this->db->set($data)->where(array($this->tablekey => $params[$this->tablekey]))->update($this->tablename);
            }else{
                $data = array_merge($data,array(
                    'insert_time' => time(),
                    'insert_uid' => $this->user->id,
                    'insert_user' => $this->user->name
                ));
                $this->db->set($data)->insert($this->tablename);
                //echo $this->db->last_auery();
            }
        }
        json_success(lang('success'));
    }

    /*
    Anthor：shihaijuan
    Date：2021-12-15
    Desc：逻辑删除
    */
    public function delete() {
        $row = $this->db->where('id', $this->input->get('id'))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->set('is_delete', 1)->where('id', $this->input->get('id'))->update($this->tablename);
        json_success(lang('success'));
    }

    //发广播
    public function push() {        
        if (!$this->push_permission()) json_fail (lang('permission_fail'));
        $row = $this->db->where('id', $this->input->post('id'))->get($this->tablename)->row();
        //这里插入广播任务
        $this->message_model->insert(array(
            "to_uid" => 1001,
            "param" => $row->id,
            "title" => lang('msg_notice_new'),
            "description" => $row->title,
            "module" => $this->tablename
        ));
        json_success(lang('success'));
    }
    
    //验证权限
    public function permission($row) {
        $result = false;
        if (!$row) json_fail (lang('undefined'));
        if ($this->user->id == $row->insert_uid) {
            $result = true;
        }
        if (!$result) json_fail (lang('permission_fail'));
    }
    
    //验证广播权限
    public function push_permission() {
        return in_array($this->user->id,array(1001,1002));
    }
}
