<?php
namespace App\Clients;

use Seriti\Tools\Table;

class ClientFixed extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Fixed invoice item','col_label'=>'name','pop_up'=>true];
        parent::setup($param);       

        //NB: master_col_idX shoudl be replaced by suitable master table cols you wish to use
        $this->setupMaster(array('table'=>TABLE_PREFIX.'client','key'=>'client_id','label'=>'name','child_col'=>'client_id', 
                                'show_sql'=>'SELECT CONCAT("Client:",`name`) FROM `'.TABLE_PREFIX.'client` WHERE `client_id` = "{KEY_VAL}" '));  

        $this->addTableCol(array('id'=>'fixed_id','type'=>'INTEGER','title'=>'Fixed ID','key'=>true,'key_auto'=>true));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Name'));
        $this->addTableCol(array('id'=>'quantity','type'=>'INTEGER','title'=>'Quantity','new'=>1));
        $this->addTableCol(array('id'=>'price','type'=>'DECIMAL','title'=>'Price'));
        $this->addTableCol(array('id'=>'repeat_period','type'=>'STRING','title'=>'Repeat'));
        $this->addTableCol(array('id'=>'repeat_date','type'=>'DATE','title'=>'Repeat date'));

        $this->addAction(array('type'=>'check_box','text'=>''));
        $this->addAction(array('type'=>'edit','text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));

        $this->addSearch(array('name','price','repeat_date','repeat_period'),array('rows'=>2));

        $this->addSelect('repeat_period','(SELECT "MONTHLY") UNION (SELECT "ANNUALY")'); 
    }  
}