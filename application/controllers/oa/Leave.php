<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Leave extends MY_Controller {
    
    public $tablename = 'oa_leave';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    /*
    Anthor：shihaijuan
    Date：2021-10-22
    Desc：列表，分页
    状态，check,recheck,success,delete
    */
    public function index() {
        $current = $this->input->get("current") ? $this->input->get("current") : 1;
        $pageSize = $this->input->get("pageSize") ? $this->input->get("pageSize") : config_item('page_per');   
        $admin=$this->input->get("admin");
        if($admin==1){

        }else{
            $this->db->where("(`insert_uid`={$this->user->id} OR `department_id`={$this->user->department_id})");
        }
        
        switch ($this->input->get("tab")) {
            case 'approval': 
                $this->db->where("(`status`='check' OR status='recheck')");
                break;
            case 'success':
                $this->db->where('status', 'success');
                break;
        }
        if ($this->input->get("keywords")){
            $this->db->like('insert_user',$this->input->get("keywords"));
        }
        if ($this->input->get("thing")){
            $this->db->where('thing',$this->input->get("thing"));
        }
        if ($this->input->get("name")){
            $this->db->like('insert_user',$this->input->get("name"));
        }
        $this->db->order_by('insert_time desc,id desc');
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($pageSize, ($current-1) * $pageSize)->get()->result();
        foreach($rows as $row){
            $row = $this->detail_extend($row);
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
    Date：2021-10-22
    Desc：详情
    */
    public function detail() {
        $row = $this->db->where($this->tablekey,$this->input->post_get($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $row = $this->detail_extend($row);   
        $row ? json_success(array("row" => $row)) : json_fail(lang('undefined'));
    }

    public function detail_extend($row) {
        if (!$row) return ;
        $setting=$this->_setting();
        $things=$setting['things'];
        $insert_user = $this->user_model->row($row->insert_uid);
        $row->insert_avatar = $insert_user?$insert_user->avatar:"https://gw.alipayobjects.com/zos/rmsportal/BiazfanxmamNRoxxVxka.png";
        $row->thing_str = element($row->thing,$things);
        $row->insert_time_str = time_convert($row->insert_time);
        return $row;
    }

    /*
    Anthor：shihaijuan
    Date：2021-10-11
    Desc：增删改
    */
    public function save(){
        $params=file_get_contents("php://input");
        $params = json_decode($params,true);
        $data = array(
            'thing'=>$params['thing'],
            'start_time'=>strtotime($params['start_time']),
            'end_time'=>strtotime($params['end_time']),
            'reason'=>$params['reason'],
            'insert_time' => time(),
            'insert_uid' => $this->user->id,
            'insert_user' => $this->user->name,
            'department_id' => $this->user->department_id,
            'department_name' => $this->user->department_name,
            'status'=>'check'
        );
        $this->db->set($data)->insert($this->tablename);
        $id= $this->db->insert_id();
       //审批
        $this->approval_model->insert(array(
            'rule' => 'kaoqin',
            'tablename' => $this->tablename,
            'parent' => $id,
            'tablename_zh_cn'=>'外出请假'
        ));
        json_success(lang('success'));
    }

    public function test(){
        $this->approval_model->insert(array(
            'rule' => 'kaoqin',
            'tablename' => $this->tablename,
            'parent' => 14,
            'tablename_zh_cn'=>'外出请假'
        ));
    }
    
    /*
    Anthor：shihaijuan
    Date：2021-12-14
    Desc：物理删除
    */
    public function delete() {
        $row = $this->db->where($this->tablekey,$this->input->get($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->where($this->tablekey,$this->input->get($this->tablekey))->delete($this->tablename);
        json_success(lang('success'));
    }

    /*
    Anthor：shihaijuan
    Date：2021-11-16
    Desc：判断权限
    */
    public function permission($row) {
        $result = false;
        if (!$row) json_fail (lang('undefined'));
        if ($this->user->id == $row->insert_uid) {
            $result = true;
        }        
        //if (!$result) json_fail (lang('permission_fail'));
    }


    /*
    Anthor：shihaijuan
    Date：2021-12-15
    Desc：首页显示请假人员
    */
    public function home() {
        $this->db->where('end_time >=',time())->order_by('id desc');//->where('area !=','factory')
        $rows = $this->db->get('oa_leave')->result();
        foreach($rows as $row){
            $row = $this->detail_extend($row);
        }
        json_success($rows);
    }



    public function setting() {
        json_success($this->_setting());
    }

    private function _setting() {
        return array(
            'things' => array(            
                    'shijia' => '事假',
                    'bingjia' => '病假',
                    'chuchai' => '出差',
                    'yingongwaichu' => '因公外出',
                    'tiaoxiu' => '调休',
                    'jiaban' => '加班',
                    'hunjia' => '婚假',
                    'chanjia' => '产假',
                    'chanjianjia' => '产检假',
                    'peichanjia' => '陪产假',
                    'qita' => '其它'
                )
        );
    }
}