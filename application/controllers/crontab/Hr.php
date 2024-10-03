<?php

defined('BASEPATH') OR exit('No direct script access allowed');
define('SEASON', 'summer'); //夏令时summer,冬令时winter

class Hr extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('Weixinapi');
    }

    public function index() {
        /* 合同 */
        $this->contract();
        exit('api crontab hr success');
    }

    //判断是否节假日
    public function is_holiday() {
        if (date('w') == 6 || date('w') == 0 || date('w') == 7) {
            return true;
        }
        return false;
    }
    //合同1个月到期提醒
    public function contract() {
        $cache_key = date('Ymd') . 'hr_contract_xuyue';
        if (!$this->cache->memcached->get($cache_key) && date('H') == 10 && !$this->is_holiday()) {
            $tablename = "hr_contract";
            $approvals = $this->db->where(array(
                        'tablename' => $tablename,
                        'type' => "remind",
                        'status' => "TODO"
                    ))->get("base_approval")->result();
            foreach ($approvals as $approval) {
                $parent = $approval->parent;
                $contract = $this->db->where("id", $approval->parent)->get("hr_contract")->row();
                $user = $contract ? cache_user($contract->uid) : array();
                if ($user) {
                    $data = 1;
                    /*少了业务的实际判断
1、合同到期大于1个月，不提醒，这个流程不走，data=0
2、合同到期小于等于1个月，走这个流程，data=1
                    */
                    $approval_status = "check";
                    //更新当前审批流SUCCESS or FAIL
                    $this->db->where("id", $approval->id)->set(array(
                        'status' => ($data == 1) ? "SUCCESS" : "FAIL",
                        'update_time' => time()
                    ))->update("base_approval");
                    //处理下一个审批流
                    $next = $this->db->where(array(
                                'tablename' => $tablename,
                                'parent' => $parent,
                                'step' => $approval->step + 1))->order_by("step asc")->get("base_approval")->row();
                    if ($next) {
                        //如果下一流程是WAIT，去通知，有可能超时打印，下一流程不是WAIT，这种情况不需要多余操作
                        if ($next->status == "WAIT") {
                            //下一个审批流为TODO
                            $this->db->where("id", $next->id)->set(array('status' => "TODO"))->update("base_approval");
                            $approval_status = $next->type;
                            //通知下一个审批人
                            $description = $user->realname . '的合同即将到期，请您确认是否续约！';
                            $message = array(
                                "module" => $tablename,
                                "param" => $parent,
                                "title" => "合同到期续约提醒",
                                "description" => $description,
                                'to_uid' => $next->forward_uid
                            );
                            $this->message_model->insert($message);
                        }
                    } else {
                        $approval_status = "success";
                    }
                    //更新业务中的approval_status
                    $this->db->where("id", $parent)->set(array('approval_status' => $approval_status))->update($tablename);
                    
                }
            }
            $this->cache->memcached->save($cache_key, true, 86400);
        }
    }
    
    //合同1个月到期提醒多次提醒
    public function contract_multi() {
        
    }
}
