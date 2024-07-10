<?php

class Config {
    public $default_schedule_type; 
    public $min_transaction_amount;
    public $max_transaction_amount; 
    public $max_installment_volume; 
    public $max_installment_terms;
    public $allow_direct_payment;
    public $max_transaction_volume;


    public function __construct($args){
        if(is_array($args)) {
            foreach($args as $key => $value) {
                if(property_exists($this, $key)) {
                    $this->{$key} = $value;
                } else {
                    throw new Exception("Property {$key} does not exist in Config class");
                }
            }
        }
    }
}
