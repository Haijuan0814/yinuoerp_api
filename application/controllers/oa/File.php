<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class File extends MY_Controller {
    
    public $tablename = 'oa_file';
    public $tablekey = 'id';
    public $shouce_parent = 1;

    public function __construct() {
        parent::__construct();
    }
    
/*
    Anthor：shihaijuan
    Date：2021-10-11
    Desc：列表，分页
    */
    public function index() {
        $current = $this->input->get("current") ? $this->input->get("current") : 1;
        $pageSize = $this->input->get("pageSize") ? $this->input->get("pageSize") : config_item('page_per');   

        /*按照标题搜索*/
        if($this->input->get('title')){
            $this->db->where('(`title` like "%'.$this->input->get('title').'%")');
        }
        /*安装状态搜索*/
        if(strlen($this->input->get('status'))>0){
            $this->db->where('status',$this->input->get('status'));
        }
        /*按照指定标签搜索*/
        $tab=$this->input->get('tab');
        if($tab=="created"){
            $this->db->where('insert_uid',$this->user->id);
        }else if($tab=="related"){
            $this->db->where("(FIND_IN_SET('{$this->user->id}',`deal_uids`) and 1=1)");
        }
        $this->db->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($pageSize, ($current-1) * $pageSize)->get()->result();
        foreach($rows as $row){
            $this->detail_extend($row);
        }
        json(array(
            "data" => $rows,
            "total" => $total,
            'current' => $current,
            'pageSize' => $pageSize,
        ));
    }


    /*
    Anthor：shihaijuan
    Date：2021-10-14
    Desc：手册列表，不分页
    */
    public function shouce() {
        /*按照标题搜索*/
        if($this->input->get('title')){
            $this->db->where('(`title` like "%'.$this->input->get('title').'%")');
        }
        $this->db->select('id,title,content,parent,insert_uid,update_time')->where('(parent !=0 and 1=1)')->order_by('listorder asc,id asc');
        $rows = $this->db->get($this->tablename)->result();
        $parent_keys=array();
        foreach($rows as $row){
            $row->key=$row->id;
            $row->children=array();
            if($row->parent==$this->shouce_parent) $parent_keys[]=$row->id;
        }
        $rows=json_foreach(json_decode(json_encode($rows),true),$this->shouce_parent);
        json_success(array('rows'=>$rows,'parent_keys'=>$parent_keys));//$rows
    }


    /*
    Anthor：shihaijuan
    Date：2021-10-11
    Desc：增删改
    */
    public function form(){
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        $method=$_SERVER['REQUEST_METHOD'];
        if($method=='POST'){
            $data = array();
            foreach (array('title','content') as $field) {
                $data[$field] = $params[$field];
            }
            $data['parent']=isset($params['parent'])?$params['parent']:$this->shouce_parent;
            $data['update_time']=time();
            if (isset($params[$this->tablekey])&&$params[$this->tablekey]) {
                $row = $this->db->where($this->tablekey,$params[$this->tablekey])->get($this->tablename)->row();
                //$this->permission($row);
                $openid=$params[$this->tablekey];
                $this->db->set($data)->where(array($this->tablekey => $params[$this->tablekey]))->update($this->tablename);
            }else{
                $data = array_merge($data,array(
                    'insert_time' => time(),
                    'insert_uid' => $this->user->id,
                    'insert_user' => $this->user->name
                ));
                $this->db->set($data)->insert($this->tablename);
                echo $this->db->last_query();
            }
        }else if($method=='update'){

        }else if($method=='delete'){
            $this->db->where($this->tablekey,$params[$this->tablekey])->delete($this->tablename);
        }
        //json_success(lang('success'));
    }

    public function detail() {
        $row = $this->db->where($this->tablekey,$this->input->get($this->tablekey))->get($this->tablename)->row();
        $row ? json_success(array("row" => $row)) : json_fail(lang('undefined'));
    }

    
    public function save() {
        $data = array();
        foreach (array('title','content') as $field) {
            $data[$field] = $this->input->post($field);
        }
        if ($this->input->post($this->tablekey)) {
            $this->db->set($data)->where(array($this->tablekey => $this->input->post($this->tablekey)))->update($this->tablename);
        }else{
            $data = array_merge($data,array(
                'insert_time' => time(),
                'uid' => $this->user->id
            ));
            $this->db->set($data)->insert($this->tablename);
        }
        json_success(lang('success'));
    }
    
    public function delete() {
        $row = $this->db->where($this->tablekey,$this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->set('is_delete',1)->where($this->tablekey,$this->input->post($this->tablekey))->update($this->tablename);
        json_success($row);
    }
}