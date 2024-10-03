<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Search extends MY_Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        $page = $this->input->get("page") ? $this->input->get("page") : 0;
        if (!$this->input->get("keyword"))  json_fail(lang('required_fail'));
        $data = array();
        
        //-------------手册部分-------------
        $this->db->select('id,title')->like('title', $this->input->get("keyword"));
        $total = $this->db->count_all_results('oa_article', false);
        $rows = $this->db->limit(config_item('page_per'), $page * config_item('page_per'))->get()->result();        
        foreach ($rows as $row) {
            $row->url = '/oa/guide?id=' . $row->id;
        }
        $data['rows'] =  $rows;
        $data['total'] =  $total;        
        /*        
        //-------------方案部分-------------
        $this->db->select('openid,title')->like('title', $this->input->get("keyword"));
        $rows = $this->db->get('oa_docs')->result();        
        foreach ($rows as $row) {
            $row->url = '/#/oa/docs/detail/' . $row->openid;
        }
        $data['docs'] =  $rows;
        //-------------用户部分-------------
        $this->db->select('id,realname')->like('realname', $this->input->get("keyword"));
        $rows = $this->db->get('user')->result();
        foreach ($rows as $row) {
            $row->url = '/#/base/user/detail/' . $row->id;
        }
        $data['user'] =  $rows;         
         */        
        json_success($data);
    }
}