<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Caigou extends MY_Controller {
    
    public $tablename = 'oa_caigou';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
}