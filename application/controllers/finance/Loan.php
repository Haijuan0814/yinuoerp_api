<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Loan extends MY_Controller {

    public $tablename = 'finance_loan';
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
        foreach (array('department_id', 'books_id', 'loan_time', 'reason', 'amount', 'classify', 'dai_name', 'account_id') as $field) {
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
            $id= $this->db->insert_id();
            $this->log_model->insert($this->tablename, $id, "新增一条记录");
        }

        if ($data['classify'] == "借")
            $rule = "loan_jie";
        else if ($data['classify'] == "贷")
            $rule = "loan_dai";
        //审批
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

    //借款-支付（回调）
    public function pay() {
        $id = $this->input->post("parent");
        $row = $this->db->where("id", $id)->get($this->tablename)->row();
        $this->permission($row);
        $this->db->set(array("pay_pictures" => $this->input->post("pay_pictures"), 'account_id' => $this->input->post("account_id")))->where("id", $id)->update($this->tablename);

        //现金流水，账户支出
        $item = array(
            'account_id' => $this->input->post("account_id"),
            'remark' => $this->input->post("remark"),
            'tablename' => $this->tablename,
            'parent' => $id,
            'amount' => 0 - $row->amount,
            'books_id' => $row->books_id
        );
        $this->account_model->insert($item);
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
        if ($this->user->id == $row->uid || in_array($this->user->id, $this->setting_model->item("finance_admin", true))) {
            $result = true;
        }
        if (!$result)
            json_fail(lang('permission_fail'));
    }

    //借款或者贷款，冲减
    public function offset() {
        $id = $this->input->post("id");
        $row = $this->db->where("id", $id)->get($this->tablename)->row();
        $this->permission($row);
        $offset = $this->input->post("offset_amount");
        $offset_remark = $this->input->post("offset_remark");
        $account_id = $this->input->post("account_id");

        if (!$account_id) {
            json_fail("请选择转入账户！");
        }
        if ($row->amount < ($row->offset + $offset)) {
            if ($row->classify == "借") {
                json_fail("冲减金额大于借款金额！");
            } else if ($row->classify == "贷") {
                json_fail("还款金额大于贷款金额！");
            }
        } else {
            $this->db->set(array(
                "loan_id" => $id,
                "uid" => $this->user->id,
                "insert_time" => time(),
                "amount" => $offset,
                'account_id' => $account_id,
                "remark" => $offset_remark,
            ))->insert("finance_loan_offset");
            $this->db->set(array("offset" => $row->offset + $offset))
                    ->where("id", $id)->update($this->tablename);
            /* 添加到流水  关联现金银行开始 */
            if ($row->classify == "借") {
                $account_amount = $offset;
            } else if ($row->classify == "贷") {
                $account_amount = -$offset; //还贷
            }
            $item = array(
                'account_id' => $account_id,
                'remark' => $offset_remark,
                'tablename' => "finance_loan_offset",
                'parent' => $id, //仍然为借款单编号ID
                'amount' => $account_amount,
                'books_id' => $row->books_id
            );
            $this->account_model->insert($item);
        }
        $this->log_model->insert($this->tablename, $id, "还款" . $offset);
        json_success(lang('success'));
    }

}
