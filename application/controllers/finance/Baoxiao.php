<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Baoxiao extends MY_Controller {

    public $tablename = 'finance_baoxiao';
    public $tablekey = 'id';
    public $detailtablename = 'finance_baoxiao_detail';

    public function __construct() {
        parent::__construct();
    }

    public function index() {
        $page = $this->input->get("page") ? $this->input->get("page") : 0;
        if ($this->input->get('id')) {
            $this->db->like('id', $this->input->get('id'));
        }
        if ($this->input->get('department_id')) {
            $this->db->where("department_id", $this->input->get('department_id'));
        }
        if ($this->input->get('books_id')) {
            $this->db->where('books_id', $this->input->get('books_id'));
        }
        if ($this->input->get('uid')) {
            $this->db->where('uid', $this->input->get('uid'));
        }
        if ($this->input->get('keyword')) {
            $this->db->where("(remark like '%" . $this->input->get('keyword') . "%' or `caigou_ids` like '%" . $this->input->get('keyword') . "%')");
        }
        if ($this->input->get("tab")) {
            $this->db->where("approval_status", $this->input->get("tab"));
        }else{
            $this->db->where("approval_status !=", 'delete');
        }
        $start_date = $this->input->get("start_time");
        $end_date = $this->input->get("end_time");
        if ($start_date) {
            $this->db->where("create_time>=",$start_date);
        }
        if ($end_date) {
            $this->db->where("create_time<=",$end_date);
        }
        $this->db->select("id,department_id,books_id,is_gongzika,total,remark,approval_status,uid,create_time");
        $this->db->order_by("create_time desc");
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit(config_item('page_per'), $page * config_item('page_per'))->get()->result();
        $settings= $this->_setting();
        foreach ($rows as $row) {
            $row = $this->detail_extend($row);
        }
        json_success(array(
            "rows" => $rows,
            "total" => $total,
            'page' => $page,
        ));
    }

    public function detail() {
        if($this->user->id==1399&&false){
            $row = $this->db->where('id',233084)->get("base_approval")->row();
            //触发回调
            echo $business_callback = modules::run("finance/baoxiao/pay");
            //echo Modules::load($row->business_url);
            
            exit(1);
            //exit($business_callback);
        }
        $row = $this->db->where(array($this->tablekey => $this->input->get($this->tablekey)))->get($this->tablename)->row();
        //$this->permission($row);
        $row = $this->detail_extend($row);
        $row ? json_success($row) : json_fail(lang('undefined'));
    }

    public function detail_extend($row) {
        if (!$row)
            return;
        $department = $this->db->select('name')->where("id", $row->department_id)->get("base_department")->row();
        $row->department_name = $department ? $department->name : "";
        $books = $this->db->select('name')->where("id", $row->books_id)->get("finance_books")->row();
        $row->books_name = $books ? $books->name : "";
        $row->insert_user = cache_user($row->uid);
        $settings=$this->_setting();
        $row->gongzika=element($row->is_gongzika,$settings['is_gongzika']);
        $row->status=element($row->approval_status,$settings['tabs']);
        $details = $this->db->where("baoxiao_id", $row->id)->get($this->detailtablename)->result();
        foreach($details as $detail){
            $subject=$this->db->select('name')->where("id", $detail->subject_id)->get("finance_subjects")->row();
            $detail->subject_name = $subject ? $subject->name : "";
        }
        $row->details=$details;
        return $row;
    }

    public function save() {
        $data = array();
        foreach (array('department_id', 'books_id', 'is_free_audit', 'remark', 'is_gongzika', 'attachments','details','total') as $field) {
            $data[$field] = $this->input->post($field);
        }
        if ($this->input->post($this->tablekey)) {
            $this->db->set($data)->where(array($this->tablekey => $this->input->post($this->tablekey)))->update($this->tablename);
            $id = $this->input->post($this->tablekey);
            $this->log_model->insert($this->tablename, $id, "修改这条记录");
        } else {
            $data = array_merge($data, array(
                'create_time' => time(),
                'uid' => $this->user->id
            ));
            $this->db->set($data)->insert($this->tablename);
            $id= $this->db->insert_id();
            $this->log_model->insert($this->tablename, $id, "新增一条记录");
        }
        
        
        $details= json_decode($this->input->post("details"),true);
        $detail_ids=array();
        foreach($details as $detail){
            $detail_data=array(
                'baoxiao_id' => $id,
                'books_id' => $data['books_id'],
                'title' => $detail['title'],
                'subject_id' => $detail['subject_id'],
                'amount' => $detail['amount'],
            );
            if ($detail['id']) {
                $detail_ids[] = $detail['id'];
                $this->db->where('id', $detail['id'])->set($detail_data)->update($this->detailtablename);
            } else {
                $this->db->set($detail_data)->insert($this->detailtablename);
                $detail_ids[] = $this->db->insert_id();
            }
        }
        $this->db->where("baoxiao_id", $id)->where_not_in("id", $detail_ids)->delete($this->detailtablename);
        if(!$this->input->post($this->tablekey)){
            $rule = "baoxiao";
            if ($this->input->post('is_free_audit') == 1)
                $rule = "baoxiao_guanli";
            //审批
            $this->approval_model->insert(array(
                'rule' => $rule,
                'tablename' => $this->tablename,
                'parent' => $id,
                'tablename_zh_cn'=>'报销'
            ));
        }
        json_success(array('id' => $id,'msg'=>lang('success')));
    }

    //付款回调
    public function pay() {
        echo "pay";
        $parent=$this->input->post("parent");
        $row = $this->db->where("id",$parent)->get($this->tablename)->row();
        $this->permission($row);
        $pay_content=$this->input->post("account_id");
        $pay_content= json_decode($pay_content,true);
        $total = 0;
        if(!$pay_content){
            json_fail("支付账户不能为空！");
        }else{
            foreach ($pay_content as $k=>$v) { $total += $v['amount']; }
            if ($total != floatval($row->total)) {
                json_fail("支付金额与报销金额不一致！");
            } else{
                foreach ($pay_content as $k=>$v) {
                    $account_id=$v['account_id'];
                    $amount=-$v['amount'];
                    $item = array(
                        'books_id'=>$row->books_id,
                        'account_id'=>$account_id,
                        'amount'=>$amount,
                        'remark'=>$this->input->post("remark"),
                        'tablename'=>$this->tablename,
                        'parent'=> $parent,
                    );
                    $this->account_model->insert($item);
                }
                // json_success(lang('success'));
                //现金预算模块，暂时忽略
            }
        }
    }
    
    //打印回调
    public function _print(){
        $id=$this->input->post("parent");
        $row = $this->db->where("id",$id)->get($this->tablename)->row();
        $this->db->set(array("print_num"=>$row->print_num+1))->where("id",$id)->update($this->tablename);
        json_success(lang('success'));
    }
    
    //打印模板
    public function print_template(){
        $row = $this->db->where(array($this->tablekey => $this->input->post($this->tablekey)))->get($this->tablename)->row();
        $row = $this->detail_extend($row);
        if(!$row) json_fail(lang('undefined'));
        $html=$this->load->view('print/baoxiao',array('one'=>$row),true);
        json_success($html);     
    }

    //作废
    public function delete() {
        $row = $this->db->where($this->tablekey, $this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->where($this->tablekey, $this->input->post($this->tablekey))->set(array('approval_status'=>"delete"))->update($this->tablename);
        json_success(lang('success'));
    }

    public function permission($row) {
        $result = false;
        if (!$row)
            json_fail(lang('undefined'));
        if ($this->user->id == $row->uid||in_array($this->user->id, $this->setting_model->item("finance_admin",true))) {
            $result = true;
        }
        if (!$result)
            json_fail(lang('permission_fail'));
    }
    
    public function setting() {
        json_success($this->_setting());
    }

    private function _setting() {
        return array(
            'tabs' => array(
                '' => '全部',            
                'check' => '待审批',
                'print' => '待打印',
                'pay' => '待付款',
                'success' => '处理成功',
                'fail' => '已驳回',
                'delete' => '已作废',
            ),
            'is_gongzika'=> array(
                1 => '工资卡',            
                2 => '内转',
                0 => '其它',
            ),
        );
    }

}
