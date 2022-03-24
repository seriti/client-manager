<?php 
namespace App\Clients;

use Seriti\Tools\Table;

class CreditItem extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Credit Item','col_label'=>'item','pop_up'=>true];
        parent::setup($param);        
                       
        //NB: specify master table relationship
        $this->setupMaster(array('table'=>TABLE_PREFIX.'invoice','key'=>'credit_id','child_col'=>'credit_id', 
                                 'show_sql'=>'SELECT CONCAT("Credit Note: ",`credit_no`) FROM `'.TABLE_PREFIX.'credit` WHERE `credit_id` = "{KEY_VAL}" '));                        

        $access['read_only'] = true;                         
        $this->modifyAccess($access);

        $this->addTableCol(array('id'=>'data_id','type'=>'INTEGER','title'=>'Data ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'quantity','type'=>'INTEGER','title'=>'Quantity'));
        $this->addTableCol(array('id'=>'item','type'=>'STRING','title'=>'Invoice item'));
        $this->addTableCol(array('id'=>'price','type'=>'DECIMAL','title'=>'Price'));
        $this->addTableCol(array('id'=>'total','type'=>'DECIMAL','title'=>'Total'));

        $this->addSortOrder('T.`data_id`','Order of capture','DEFAULT');
    }    
}