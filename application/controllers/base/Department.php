<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Department extends MY_Controller {
    
    public $tablename = 'user_department';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        $search=$this->input->get("search");
        $search=json_decode($search);
        if($search){
            foreach($search as $k=>$v){
                if($k=="name"&&$v){
                    $this->db->like('name',$this->input->get("name"));
                }
                if($k=="is_key"&&$v){
                    $this->db->where("is_key",1);
                }
            }
        }
        
        $this->db->where('is_delete',0)->order_by('listorder desc');
        $this->db->select("id,name,parent,listorder,is_key");
        $rows = $this->db->get($this->tablename)->result();
        foreach($rows as $row){
            $row= $this->detail_extend($row);
        }
        json_success(array(
            "rows" => $rows,
        ));
    }
    
    public function detail() {
        $row = $this->db->select("id,name,parent,listorder,is_key")->where(array($this->tablekey => $this->input->get($this->tablekey)))->get($this->tablename)->row();
        $row = $this->detail_extend($row);
        
        $row ? json_success($row) : json_fail(lang('undefined'));
    }
    
    public function detail_extend($row){
        if (!$row) return ;
        $where=array("is_delete"=>0,"is_leave"=>0,"department_id"=>$row->id);
        $row->user_num= $this->db->where($where)->count_all_results("user");
        //部门现有人员
        $users= $this->user_model->rows(json_encode($where),"id,name,avatar,job_name,age");
        $row->users=$users;
        return $row;
    }
    
    public function setting() {
        json_success($this->_setting());
    }

    private function _setting() {
        return array(
            'leaders' => array(
                "uid"=>'负责人',
                "adm_uid"=>'行政助理',
                "biz_uid"=>'业务助理'
            )
        );
    }
}