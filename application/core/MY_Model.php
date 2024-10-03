<?php

(defined('BASEPATH')) OR exit('No direct script access allowed');

class MY_Model extends CI_Model {
    
    public function __construct() {
        parent::__construct();
    }

    //列表
    public function rows() {
        $page = $this->input->post_get("page") ? $this->input->post_get("page") : 0;
        $per = $this->input->post_get("per") ? $this->input->post_get("per") : 20;        
        $total = $this->db->count_all_results($this->tablename, false);
        $rows = $this->db->limit($per, $page * $per)->get()->result();
        $rows = $this->rows_foreach($rows);
        return array(
            "rows" => $rows,
            "total" => $total,
            'per' => $per,
            'page' => $page,
        );
    }

    public function rows_search() {
        return;
    }

    public function rows_foreach($rows) {
        return $rows;
    }

    //读取任意记录，但实际业务中也不是这样的，需要做很多权限限制
    public function detail() {
        if ($this->input->post_get($this->tablekey)) {
            $where = array(
                $this->tablekey => $this->input->post_get($this->tablekey)
            );
            return $this->db->where($where)->get($this->tablename)->row();
        } else {
            return array();
        }
    }

    public function save() {
        $fields = $this->db->list_fields($this->tablename);
        $data = array();
        foreach ($fields as $field) {
            if (array_key_exists($field, $this->input->post()) || array_key_exists($field, $this->input->get())) {
                $data[$field] = $this->input->post_get($field);
            }
        }
        //更新内容；要么指定，要么post-get获取
        if ($data) {
            $this->db->set($data);
            $key = $this->input->post_get($this->tablekey);
            if ($key) {
                $this->db->where(array(
                    $this->tablekey => $key
                ))->update($this->tablename);
                return $key;
            } else {
                $auto_data = array();
                if (in_array('uid', $fields)) {
                    $auto_data['uid'] = $this->user->id;
                }
                if (in_array('insert_time', $fields)) {
                    $auto_data['insert_time'] = time();
                }
                $this->db->set($auto_data);
                $this->db->insert($this->tablename);
                return $this->db->insert_id();
            }
        }
    }

    public function delete() {
        $this->db->where(array(
            $this->tablekey => $this->input->post_get($this->tablekey)
        ))->delete($this->tablename);
        return $this->db->affected_rows();
    }

}
