<?php

defined('BASEPATH') OR exit('No direct script access allowed');

//base.user侧重于全局服务； 
//hr.user是员工管理

class Home extends MY_Controller {

    public function __construct() {
        parent::__construct();
        
        
    }
    
    /*团队成员*/
    public function user() {
        $this->db->select('id,avatar,tags,name,sex,mobile,job_name,department_name')->where('is_leave',0)->order_by('department_id asc');//->where('area !=','factory')
        $rows = $this->db->get('user')->result();
        json_success($rows);
    }

    //首页推送的通知
    public function notice() {
        $this->db->select('id,title,content')
                ->where('is_delete',0)->where('is_send',1)
                ->where('start_date <=',strtotime(date('Ymd')))->where('end_date >=',strtotime(date('Ymd')))
                ->order_by('id desc');//->where('area !=','factory')
        $rows = $this->db->get('oa_notice')->result();
        json_success($rows);
    }
}