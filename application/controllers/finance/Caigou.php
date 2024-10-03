<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Caigou extends MY_Controller {

    public $tablename = 'finance_caigou';
    public $tablekey = 'id';

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
        if ($this->input->get('insert_name')) {
            $this->db->like('insert_name', $this->input->get('insert_name'));
        }
        if ($this->input->get('sn')) {
            $this->db->like('sn', $this->input->get('sn'));
            //$keyword=$this->input->get('keyword');
            //$this->db->where('(destination like "%'.$keyword.'%") ');
        }
        if ($this->input->get("status")) {
            $this->db->where("status", $this->input->get("status"));
        }
        $this->db->order_by("insert_time desc");
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit(config_item('page_per'), $page * config_item('page_per'))->get()->result();
        
        foreach ($rows as $row) {
            $this->detail_extend($row);
        }
        json_success(array(
            "rows" => $rows,
            "total" => $total,
            'page' => $page,
        ));
    }

    public function detail() {
        $row = $this->db->where(array($this->tablekey => $this->input->get($this->tablekey)))->get($this->tablename)->row();
        $this->permission($row);
        $row = $this->detail_extend($row);
        $row ? json_success($row) : json_fail(lang('undefined'));
    }

    public function detail_extend($row) {
        if (!$row)
            return;
        $settings= $this->_setting();
        $department = $this->department_model->row($row->department_id);
        $row->department_name = $department ? $department->name : "";
        $row->vehicle_name = element($row->vehicle,$settings['vehicle']);
        $row->status=element($row->approval_status,$settings['tabs']);
        $row->insert_user = cache_user($row->uid);
        return $row;
    }

    public function save() {
        $data = array();
        foreach (array('department_id', 'destination', 'reason', 'departure_time', 'back_time', 'vehicle','remark','retention','retention_range','travelers') as $field) {
            $data[$field] = $this->input->post($field);
        }
        $vehicle=$this->input->post("vehicle");
        $travelers=$this->input->post("travelers");
        $travelers= json_decode($travelers,true);
        $traveler_uids=array();
        foreach ($travelers as $k=>$v){ $traveler_uids[]=$v["id"];}
        if($vehicle==6){//自驾车判断
            $is_self_driving=$this->db->where('is_self_driving',1)->where_in('id',$traveler_uids)->count_all_results("base_user");
            if(!$is_self_driving){
                json_fail("本次出差人员不允许自驾车出差！");
            }
        }
        if ($this->input->post($this->tablekey)) {
            $this->db->set($data)->where(array($this->tablekey => $this->input->post($this->tablekey)))->update($this->tablename);
            $id=$this->input->post($this->tablekey);
            $this->log_model->insert($this->tablename, $id, "修改这条记录");
        } else {
            $data = array_merge($data, array(
                'create_time' => time(),
                'uid' => $this->user->id
            ));
            $this->db->set($data)->insert($this->tablename);
            $id=$this->db->insert_id();
            $this->log_model->insert($this->tablename, $id, "新增一条记录");
        }
        $rule = "chuchai";
        /*公司用车和自行购买、自驾车，不需要采购*/
        if ($vehicle!=99&&$vehicle!=5&&$vehicle!=6) $rule = "chuchai_unbuy";
        //审批
        $this->approval_model->insert(array(
            'rule' => $rule,
            'tablename' => $this->tablename,
            'parent' => $id,
        ));
        json_success(lang('success'));
    }

    
    //购买车票回调
    public function buy() {
        $parent=$this->input->post("parent");
        $this->db->set(array(
            "puchase_amount"=>$this->input->post("puchase_amount"),
        ))->where("id",$parent)->update($this->tablename);
        json_success(lang('success'));
    }
    
    //打印回调
    public function _print(){
        $id=$this->input->post("parent");
        $row = $this->db->where("id",$id)->get($this->tablename)->row();
        $this->db->set(array("print_num"=>$row->print_num+1))->where("id",$id)->update($this->tablename);
        json_success(lang('success'));
    }

    public function delete() {
        $row = $this->db->where($this->tablekey, $this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->where($this->tablekey, $this->input->post($this->tablekey))->delete($this->tablename);
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
                'buy' => '待购票',
                'print' => '待打印',
                'success' => '处理成功',
                'fail' => '已驳回',
                'delete' => '已作废',
            ),
            'vehicle'=> $this->setting_model->item("chuchai_vehicle",true),
        );
    }

}
