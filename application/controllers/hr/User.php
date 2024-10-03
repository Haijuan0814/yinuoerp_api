<?php

defined('BASEPATH') OR exit('No direct script access allowed');

//base.user侧重于全局服务； 
//hr.user是员工管理

class User extends MY_Controller {
    
    public $tablename = 'user';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
        
    }
    private function mode($param=""){
        $select = 'id,name,avatar,department_name,job_name,join_time,is_fulltime,tags,roles';
        $select_card = ',mobile,signature,birthday,age,sex,address' ;
        $select_all = ',mobile,department_id,job_id,signature,birthday,age,sex,address,idcard,salary_card' ;
        if ($param == 'card') $select .= $select_card;
        if ($param == 'all') $select .= $select_card. $select_all;
        return $select;
        //这边all的情况，后期需要增加身份验证或者log记录
    }
    

    /*
    Anthor：shihaijuan
    Date：2021-10-14
    Desc：列表，分页
    */
    public function index() {

        /*$this->cache->memcached->save('test', '111', 600);
var_dump($this->cache->memcached->get('test')) ;
exit();*/

        $current = $this->input->get("current") ? $this->input->get("current") : 1;
        $pageSize = $this->input->get("pageSize") ? $this->input->get("pageSize") : config_item('page_per');   
        $is_page = $this->input->get("is_page") ? $this->input->get("is_page") : true;
        if ($this->input->get("department_id")){
            $department_ids=$this->department_model->get_children_ids($this->input->get("department_id"));
            $this->db->where_in('department_id',$department_ids);
        }
        if ($this->input->get("keyword")){
            $this->db->like('name',$this->input->get("keyword"));
        }
        $mode = $this->input->get('mode');
        $select = $this->mode($mode);
        $this->db->select($select)->where('is_leave',0)->order_by('department_id');//->where('area !=','factory')
        if($is_page=="true"){
            $total = $this->db->count_all_results($this->tablename, false);
            $rows = $this->db->limit($pageSize, ($current-1) * $pageSize)->get()->result();
            foreach($rows as  $row){
                $row=$this->detail_extend($row);
            }
            json_success(array(
                "data" => $rows,
                "total" => $total,
                'current' => $current,
                'pageSize' => $pageSize,
            ));
        }else{
            $rows = $this->db->get($this->tablename)->result();
            foreach($rows as  $row){
                $row=$this->detail_extend($row);
            }
            json_success(array(
                "list" => $rows
            ));
        }
        
    }

    /*
    Anthor：shihaijuan
    Date：2021-10-14
    Desc：下拉菜单，不支持分页，必须带入keyword
    */
    public function select() {
        //if (!$this->input->get("keyword")) json_fail(lang('required_fail'));
        //$this->db->like('realname',$this->input->get("keyword"));
        $this->db->select('id,name,avatar,department_name,job_name')->where('is_leave',0)->order_by('id');
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
    
    //卡片
    public function detail() {
        $mode = $this->input->get('mode');
        $select = $this->mode($mode);
        $id = $this->input->get('id')?$this->input->get('id'):2;
        $data = array();
        if ($id && strpos($id,',')){
            foreach (explode(',',$id) as $_id){
                if ($_id){
                    $data[] = $this->detail_extend($this->user_model->row($_id,$select));
                }
            }
        }elseif ($id){
            $data[] = $this->detail_extend($this->user_model->row($id,$select));
        }else{
            $data[] = $this->detail_extend($this->user_model->row($this->user->id,$select));
        }
        json_success($data);
    }
    
    public function detail_extend($row) {
        if (!$row) return ;
        $row->tags = explode(',',$row->tags);
        $row->key =$row->id;
        if(isset($row->roles)){
            $row->roles = explode(',',$row->roles);
        }
        return $row;
    }

    /*
    Anthor：shihaijuan
    Date：2021-10-19
    Desc：增&改
    */
    public function form(){
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        $method=$_SERVER['REQUEST_METHOD'];
        if($method=='POST'){
            foreach (array('name','avatar','idcard','mobile','department_id','job_id','is_fulltime','address','signature','salary_card') as $field) {
                $data[$field] = $params[$field];
            }
            $data['join_time'] = $params['join_time']?strtotime($params['join_time']):0;   
            $data['department_name'] = $params['department_id']?$this->department_model->name($params['department_id']):"";  
            $data['job_name'] = $params['job_id']?$this->job_model->name($params['job_id']):"";   

            foreach (array('roles','tags') as $field) {
                $data[$field] = isset($params[$field])&&$params[$field]?implode(',',$params[$field]):"";
            }
            /*根据身份证计算性别、年龄、生日*/
            if($data['idcard']){
                $idcard=$data['idcard'];
                $sexint = (int)substr($idcard, 16, 1);
                $data['sex'] = $sexint % 2 === 0 ? '女' : '男';

                $bir = substr($idcard, 6, 8);
                $year = (int)substr($bir, 0, 4);
                $month = (int)substr($bir, 4, 2);
                $day = (int)substr($bir, 6, 2);
                $data['birthday'] = strtotime($year . "-" . $month . "-" . $day);

                $date = strtotime(substr($idcard, 6, 8));
                $today = strtotime('today');
                $diff = floor(($today - $date) / 86400 / 365);
                $age = strtotime(substr($idcard, 6, 8) . ' +' . $diff . 'years') > $today ? ($diff + 1) : $diff;
                $data['age'] = $age;   
            }

            if (isset($params[$this->tablekey])&&$params[$this->tablekey]) {
                $this->db->set($data)->where(array($this->tablekey => $params[$this->tablekey]))->update($this->tablename);
            }else{
                //$this->db->set($data)->insert($this->tablename);
            }
            echo $params['join_time'];
            json_success(lang('success'));
        }else if($method=='delete'){
            $this->db->where($this->tablekey,$params[$this->tablekey])->delete($this->tablename);
        }
        $this->index();
    }
    
    /*
    Anthor：shihaijuan
    Date：20220107
    Desc：完善个人资料
    */
    public function saveme(){
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        $method=$_SERVER['REQUEST_METHOD'];
        if($method=='POST'){
            foreach (array('name','avatar','idcard','mobile','address','signature','salary_card') as $field) {
                $data[$field] = $params[$field];
            }

            foreach (array('tags') as $field) {
                $data[$field] = isset($params[$field])&&$params[$field]?implode(',',$params[$field]):"";
            }
            /*根据身份证计算性别、年龄、生日*/
            if($data['idcard']){
                $idcard=$data['idcard'];
                $sexint = (int)substr($idcard, 16, 1);
                $data['sex'] = $sexint % 2 === 0 ? '女' : '男';

                $bir = substr($idcard, 6, 8);
                $year = (int)substr($bir, 0, 4);
                $month = (int)substr($bir, 4, 2);
                $day = (int)substr($bir, 6, 2);
                $data['birthday'] = strtotime($year . "-" . $month . "-" . $day);

                $date = strtotime(substr($idcard, 6, 8));
                $today = strtotime('today');
                $diff = floor(($today - $date) / 86400 / 365);
                $age = strtotime(substr($idcard, 6, 8) . ' +' . $diff . 'years') > $today ? ($diff + 1) : $diff;
                $data['age'] = $age;   
            }

            if (isset($params[$this->tablekey])&&$params[$this->tablekey]) {
                $this->db->set($data)->where(array($this->tablekey => $params[$this->tablekey]))->update($this->tablename);
            }else{
                //$this->db->set($data)->insert($this->tablename);
            }
            
            $row = $this->db->select('id,name,department_id,department_name,job_name,avatar,mobile,mix,password,token,tags,address,signature,roles')->where(array($this->tablekey => $params[$this->tablekey]))->get('user')->row();

       
        //$row->tags=explode(',',$row->tags);
        $row->roles=explode(',',$row->roles);
        
            json_success($row);
        }
    }
}