<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Payment extends MY_Controller {
    
    public $tablename = 'finance_payment';
    public $tablekey = 'id';

    public function __construct() {
        parent::__construct();
    }
}