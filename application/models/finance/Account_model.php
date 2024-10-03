<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Account_model extends CI_Model {

    public $tablename = 'finance_account';
    public $detailtablename = 'finance_account_detail';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    //插入现金流水
    public function insert($param) {
        if (!$this->db->where($param)->count_all_results($this->$detailtablename)) {
            $param["insert_time"] = time();
            $param["uid"] = $this->user->id;
            $account = $this->db->where('id', $param['account_id'])->get($this->tablename)->row();
            $balance = $account ? $account->balance : 0;
            $param["balance"] = $balance + $param["amount"];
            $this->db->set($param)->insert($this->$detailtablename);
            $this->db->query("UPDATE `finance_account` SET `balance`=`balance`+'{$balance}' WHERE `id`='{$param['account_id']}'");
        }
        return ;
    }
    
}
