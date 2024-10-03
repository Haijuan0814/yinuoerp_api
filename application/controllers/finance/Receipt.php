<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Receipt extends MY_Controller {

    public $tablename = 'finance_receipt';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }

    /*
      收款单类型：客户收款+杂费收款
      流程：审批->打印
     */

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
            $keyword = $this->input->get('keyword');
            $this->db->where('(destination like "%' . $keyword . '%" or vehicle like "%' . $keyword . '%") ');
        }
        if ($this->input->get("approval_status")) {
            $this->db->where("approval_status", $this->input->get("approval_status"));
        }
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
        $row = $this->db->where(array($this->tablekey => $this->input->post($this->tablekey)))->get($this->tablename)->row();
        $this->permission($row);
        $row = $this->detail_extend($row);
        $row ? json_success($row) : json_fail(lang('undefined'));
    }

    public function detail_extend($row) {
        if (!$row)
            return;
        $books = $this->db->where("id", $row->books_id)->get("finance_books")->row();
        $row->books_name = $books ? $books->name : "";
        $row->insert_user = cache_user($row->uid);
        return $row;
    }

    public function save() {
        $data = array();
        foreach (array('type', 'receipt_time', 'department_id', 'books_id', 'customer_id', 'remitter', 'amount', 'account_id', 'remark') as $field) {
            $data[$field] = $this->input->post($field);
        }
        if ($this->input->post($this->tablekey)) {
            $this->db->set($data)->where(array($this->tablekey => $this->input->post($this->tablekey)))->update($this->tablename);
            $this->log_model->insert($this->tablename, $id, "修改这条记录");
        } else {
            $data = array_merge($data, array(
                'insert_time' => time(),
                'uid' => $this->user->id
            ));
            $this->db->set($data)->insert($this->tablename);
            $id = $this->db->insert_id();
            $this->log_model->insert($this->tablename, $id, "新增一条记录");
        }
        //审批
        $rule = "receipt";
        $this->approval_model->insert(array(
            'rule' => $rule,
            'tablename' => $this->tablename,
            'parent' => $id,
        ));
        json_success(lang('success'));
    }

    //打印回调
    public function _print() {
        $id = $this->input->post("parent");
        $row = $this->db->where("id", $id)->get($this->tablename)->row();
        $this->permission($row);
        $this->db->set(array("print_num" => $row->print_num + 1))->where("id", $id)->update($this->tablename);
        json_success(lang('success'));
    }

    //审批回调
    public function approval() {
        $id = $this->input->post("parent");
        $data = $this->input->post("data");
        if($data==1){
            $row = $this->db->where("id", $id)->get($this->tablename)->row();
            $this->permission($row);
            //现金流水，账户收入
            $item = array(
                'account_id' => $row->account_id,
                'remark' => $row->remark,
                'tablename' => $this->tablename,
                'parent' => $id,
                'amount' => $row->amount,
                'books_id' => $row->books_id
            );
            $this->account_model->insert($item);
        }
        
        json_success(lang('success'));
    }

    public function delete() {
        $row = $this->db->where($this->tablekey, $this->input->post($this->tablekey))->get($this->tablename)->row();
        $this->permission($row);
        $this->db->set(array("is_invalid" => 1))->where($this->tablekey, $this->input->post($this->tablekey))->update($this->tablename);
        //现金流水，账户支出
        $item = array(
            'account_id' => $row->account_id,
            'remark' => "收款单[{$row->id}]作废",
            'tablename' => $this->tablename,
            'parent' => $row->id,
            'amount' => 0 - $row->amount,
            'books_id' => $row->books_id
        );
        $this->account_model->insert($item);
        json_success(lang('success'));
    }

    public function permission($row) {
        $result = false;
        if (!$row)
            json_fail(lang('undefined'));
        if ($this->user->id == $row->uid || in_array($this->user->id, $this->setting_model->item("finance_admin", true))) {
            $result = true;
        }
        if (!$result)
            json_fail(lang('permission_fail'));
    }

    /* 参加培训 */
    public function join() {
        $ids = explode(',', $this->input->post("ids"));
        $data = array(
            'train_title' => $this->input->post("train_title"),
            'is_join' => 1
        );
        $ids = explode(',', $this->input->post("ids"));
        $this->db->set($data)->where_in("id", $ids)->update($this->tablename);
        foreach ($ids as $id) {
            if ($id) {
                $this->log_model->insert($this->tablename, $id, "参加培训");
            }
        }
        json_success(lang('success'));
    }
    
    public function setting() {
        json_success($this->_setting());
    }

    private function _setting() {
        return array(
            'type_select' => $this->setting_model->get("finance_rec_types",true)
        );
    }

}
