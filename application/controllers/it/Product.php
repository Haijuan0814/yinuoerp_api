<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Product extends MY_Controller {
    
    public $tablename = 'it_product';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        $page = $this->input->get("page") ? $this->input->get("page") : 0;    
        if($this->input->get('name')){
            $this->db->where('(`name` like "%'.$this->input->get('name').'%")');
        }
        $this->db->where("is_delete",0)->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit(config_item('page_per'), $page * config_item('page_per'))->get()->result();
        json_success(array(
            "list" => $rows,
            "total" => $total,
            'current' => $page,
            'pageSize' => config_item('page_per'),
        ));
    }

    public function all() {
        if($this->input->get('name')){
            $this->db->where('(`name` like "%'.$this->input->get('name').'%")');
        }
        $this->db->where("is_delete",0)->order_by('id desc');
        $rows = $this->db->get($this->tablename)->result();
  
        json_success(array(
            "list" => $rows
        ));
    }

    /*
    Anthor：shihaijuan
    Date：2021-10-14
    Desc：下拉菜单，不支持分页
    */
    public function select() {
        $this->db->select('id,name')->where('is_delete',0)->order_by('id');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->get()->result();
        $list=array();
        foreach($rows as $row){
            $list[]=array(
                'value'=>$row->id,
                'label'=>$row->name,
            );
        }
        json_success(array(
            "list" => $list,
            "total" => $total,
        ));
    }
    


    public function detail() {
        $id = $this->input->get('id');
        //$id=4;
        $row = $id?$this->db->where("is_delete",0)->where("id",$id)->get($this->tablename)->row():array();
        json_success(array(
            "row" => $row
        ));
    }
    
    public function save1() {
        $data = array();
        foreach (array('title','type','department_ids','month','day','location','funds','num','remark','in_uids','un_in_users','effect','real_funds') as $field) {
            $data[$field] = $this->input->post($field);
        }
        $in_uids = $this->input->post('in_uids');
        $in_users="";
        if($in_uids){
            foreach ($in_uids as $k=>$uid){
                $in_users.=$this->user_model->name($uid).',';
            }
        }
        $data['in_users']=$in_users;
        if ($this->input->post($this->tablekey)) {
            $this->db->set($data)->where(array($this->tablekey => $this->input->post($this->tablekey)))->update($this->tablename);
            $id=$this->input->post($this->tablekey);
        }else{
            $data = array_merge($data,array(
                'insert_time' => time(),
                'org_uid' => $this->user->id
            ));
            $this->db->set($data)->insert($this->tablename);
            $id= $this->db->insert_id();
        }
        //审批
        $this->approval_model->insert(array(
            'rule' => "huodong",
            'tablename' => $this->tablename,
            'parent' => $id,
        ));
                    
        json_success(lang('success'));
    }

    public function save($params) {
        $data = array();
        foreach (array('name','avatar','type','website','test_website','desc') as $field) {
            if(isset($params[$field]))
                $data[$field] = $params[$field];
        }
        if (isset($params[$this->tablekey])&&$params[$this->tablekey]) {
            $this->db->set($data)->where(array($this->tablekey => $params[$this->tablekey]))->update($this->tablename);
            $id=$this->input->post($this->tablekey);
        }else{
            $data = array_merge($data,array(
                'insert_time' => time(),
                'insert_uid' => $this->user->id
            ));
            $this->db->set($data)->insert($this->tablename);
            $id= $this->db->insert_id();
        }
        json_success();
    }
    
    public function form(){
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        $method=$_SERVER['REQUEST_METHOD'];
        if($method=='POST'){
            $this->save($params);
        }else if($method=='DELETE'){
            $this->db->set(array('is_delete'=>1))->where(array($this->tablekey => $params[$this->tablekey]))->update($this->tablename);
            json_success($this->db->last_query());
        }
        //json_success();
        //$this->index();
    }
    
}