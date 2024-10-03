<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Salary extends MY_Controller {
    
    public $tablename = 'user_salary';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }

    /*
    DESC：某年某月的工资单
    Author：shihaijuan
    Date：20220123
    */
    public function index(){
        //重要文件，手工验证身份ID
        if (!in_array($this->user->id, array(
            1001, //shihaijuan
            1002,//wanghaoyang
            1005 //dingqun
        ))) {
                exit('auth error');
            }
        $year=$this->input->get("year")?$this->input->get("year"):date('Y');
        $month=$this->input->get("month")?$this->input->get("month"):date('m');
        $start=strtotime($year.'-'.$month.'-01');
        $end=strtotime('+1 month',$start);
        $where='((is_leave=0 and join_time >='.$start.') or (is_leave=1 and join_time >='.$start.' and leave_time <'.$end.'))';
        
        $users=$this->db->select('id,realname')->where($where)->get('user')->result();
        $salarys=$this->db->where(array(
            'year'=>$year,
            'month'=>$month
        ))->get('user_salary')->result();
        $_uids=array();
        $_salarys=array();
        foreach($salarys as $salary){
            $_uids=$salary->uid;
            $_salarys[$salary->uid]=$salay;
        }
        foreach($users as $user){
            if(in_array($user->id,$_uids)){
                $user->salary=$_salarys[$user->id];
            }else{
                $user->salary=array();
            }
        }
        json_success(array("users" => $users));
    }
    
}