<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Books extends MY_Controller {
    
    public $tablename = 'finance_books';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        if ($this->input->get("name")){
            $this->db->like('name',$this->input->get("name"));
        }
        if ($this->input->get("department_id")){
            $this->db->where('(1=1 and FIND_IN_SET('.$this->input->get("department_id").',`department_ids`))');
        }
        $this->db->select('id,name')->where('is_delete',0)->order_by('listorder desc');
        $rows = $this->db->get($this->tablename)->result();
        json_success(array(
            "rows" => $rows,
        ));
    }
}