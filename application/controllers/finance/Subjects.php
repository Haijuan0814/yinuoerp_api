<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Subjects extends MY_Controller {
    
    public $tablename = 'finance_subjects';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        if ($this->input->get("name")){
            $this->db->like('name',$this->input->get("name"));
        }
        if ($this->input->get("type")){
            $this->db->where('type',$this->input->get("type"));
        }
        if($this->input->get('tab')!="all"){
            $this->db->where("type","期间费用");
        }
        $this->db->select('id,name,parent,remark')->where('is_delete',0)->order_by('listorder desc');
        $rows = $this->db->get($this->tablename)->result();
        json_success(array(
            "rows" => $rows,
        ));
    }
}