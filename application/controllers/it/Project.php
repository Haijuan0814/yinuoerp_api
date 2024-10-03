<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Project extends MY_Controller {
    
    public $tablename = 'it_project';
    public $tablekey = 'id';
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
        /*按照标题搜索*/
        if($this->input->get('insert_user')){
            $this->db->where('(`insert_user` like "%'.$this->input->get('insert_user').'%")');
        }
        /*安装状态搜索*/
        if(strlen($this->input->get('status'))>0){
            $this->db->where('status',$this->input->get('status'));
        }
        /*按照指定标签搜索*/
        $tab=$this->input->get('tab');
        if($tab=="ing"){
            $this->db->where('status',0);
        }else if($tab=="finish"){
            $this->db->where("(status>0 and 1=1)");
        }
        $this->db->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($pageSize, ($current-1) * $pageSize)->get()->result();
        foreach($rows as $row){
            $this->detail_extend($row);
        }
        json_success(array(
            "data" => $rows,
            "total" => $total,
            'current' => $current,
            'pageSize' => $pageSize,
        ));
    }

    /*
    Anthor：shihaijuan
    Date：2021-12-16
    Desc：放在系统首页的数据
    */
    public function home() {
        if($this->input->get('title')){
            $this->db->where('(`title` like "%'.$this->input->get('title').'%")');
        }
        if($this->input->get('product_id')){
            $this->db->where('product_id',$this->input->get('product_id'));
        }
        if(strlen($this->input->get('status'))>0){
            $this->db->where('status',$this->input->get('status'));
        }
        $this->db->where('status',0);
        $this->db->order_by('id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit(4)->get()->result();
        foreach($rows as $row){
            $this->detail_extend($row);
        }
        json_success($rows);
    }

     /*
    Anthor：shihaijuan
    Date：2021-12-08
    Desc：增删改
    */
    public function form(){
        $method=$_SERVER['REQUEST_METHOD'];
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        if($method=='POST'){
            foreach (array('title','content','status') as $field) {
                if(isset($params[$field]))
                    $data[$field] = $params[$field];
            }
            foreach (array('start_time','end_time','ui_plan_time','code_plan_time','test_plan_time','publish_plan_time') as $field) {
                if(isset($params[$field]))
                    $data[$field] = strtotime($params[$field]);
            }
            foreach (array('product_ids','ui_uids','code_uids','test_uids','publish_uids','demand_ids','code_ids') as $field) {
                if(isset($params[$field]))
                    $data[$field] = implode(',',$params[$field]);
            }
            $data['update_time']=time();
            if (isset($params[$this->tablekey])&&$params[$this->tablekey]) {
                $this->db->set($data)->where(array($this->tablekey => $params[$this->tablekey]))->update($this->tablename);
                $id=$this->input->post($this->tablekey);
            }else{
                $data = array_merge($data,array(
                    'insert_time' => time(),
                    'insert_uid' => $this->user->id,
                    'insert_user' => $this->user->name
                ));
                $this->db->set($data)->insert($this->tablename);
                $id= $this->db->insert_id();
                if(isset($params['is_code'])&&$params['is_code']==1){
                    //自动创建开发任务
                    $code_data=array(
                        'title'=>$data['title'],
                        'content'=>$data['content'],
                        'status'=>1,//立项了，默认就是开发阶段了
                        'start_time'=>$data['ui_plan_time'],
                        'end_time'=>$data['code_plan_time'],
                        'product_ids'=>$data['product_ids'],
                        'deal_uids'=>$data['code_uids'],
                        'insert_time' => time(),
                        'update_time'=>time(),
                        'insert_uid' => $this->user->id,
                        'insert_user' => $this->user->name
                    );
                    $this->db->set($code_data)->insert('it_coding');
                    $code_id= $this->db->insert_id();
                    //再将开发任务的id关联到立项中去
                    $this->db->set(array('code_ids'=>$code_id))->where(array($this->tablekey => $id))->update($this->tablename);
                    
                }
            }
        }else if($method=='DELETE'){
            $this->db->where($this->tablekey,$params[$this->tablekey])->delete($this->tablename);
        }
        json_success(lang('success'));
    }

    /*
    Anthor：shihaijuan
    Date：2021-12-08
    Desc：x详情
    */
    public function detail() {
        $id = $this->input->get('id');
        //$id=4;
        $row = $id?$this->db->where("id",$id)->get($this->tablename)->row():array();
        if($row){
            $row=$this->detail_extend($row);
        }
        json_success(array(
            "row" => $row
        ));
    }


    public function detail_extend($row) {
        if (!$row) return ;
        $products=$row->product_ids?$this->db->select("id,name,avatar")->where_in("id",explode(',',$row->product_ids))->get("it_product")->result():array();
        $row->products=$products;
        $row->ui_users=$this->user_model->names($row->ui_uids);
        $row->code_users=$this->user_model->names($row->code_uids);
        $row->test_users=$this->user_model->names($row->test_uids);
        $row->publish_users=$this->user_model->names($row->publish_uids);

        $date=$row->start_time?date('Y-m-d',$row->start_time):'-';
        $date.=($row->start_time||$row->end_time)?" 至 ":"";
        $date.=$row->end_time?date('Y-m-d',$row->end_time):'-';
        $row->date=$date;

        $row->product_ids = $row->product_ids?explode(',',$row->product_ids):array();
        $row->ui_uids = $row->ui_uids?explode(',',$row->ui_uids):array();
        $row->code_uids = $row->code_uids?explode(',',$row->code_uids):array();
        $row->test_uids = $row->test_uids?explode(',',$row->test_uids):array();
        $row->publish_uids = $row->publish_uids?explode(',',$row->publish_uids):array();


        $now=strtotime(date('Y-m-d'))-86400;
        $days = intval(($row->end_time - $row->start_time)/ 86400);
        $pass_days = intval(($now - $row->start_time)/ 86400);
        if($pass_days<0){
            $row->percent=0;
            $row->left_days=$days;
        }else{
            $row->percent=round($pass_days/$days,2)>1?100:round($pass_days/$days*100,2);
            $row->left_days=($days-$pass_days);
        }
        if($row->status>0){
            $row->percent=100;
        }
        
        $row->template='在 @{group} 新建项目 @{project}';
        return $row;
    }

    /*
    Anthor：shihaijuan
    Date：2021-12-08
    Desc：将某一节点设为完成
    */
    public function finish() {
        $id = $this->input->get('id');
        $person = $this->input->get('person');
        
        $row = $id?$this->db->where("id",$id)->get($this->tablename)->row():array();
        if($row){
            $data=array();
            switch($person){
                case 'ui':
                    $data['ui_finish_time']=time();
                    break;
                case 'code':
                    $data['code_finish_time']=time();
                    break;
                case 'test':
                    $data['test_finish_time']=time();
                    break;
                case 'publish':
                    $data['publish_finish_time']=time();
                    break;
                default:
                    break;
            }
            if($data){
                $this->db->set($data)->where(array($this->tablekey => $id))->update($this->tablename);
            }
            json_success();
        }else{
            json_fail();
        }
    }
    

    /*
    Anthor：shihaijuan
    Date：2021-12-08
    Desc：文档状态的更新
    */
    public function status() {
        $id = $this->input->get('id');
        $status = $this->input->get('status');
        $row = $id?$this->db->where("id",$id)->get($this->tablename)->row():array();
        if($row){
            $data=array(
                'status'=>$status
            );
            $this->db->set($data)->where(array($this->tablekey => $id))->update($this->tablename);
            json_success($status);
        }else{
            json_fail();
        }
    }
}