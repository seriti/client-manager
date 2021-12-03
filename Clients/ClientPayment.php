<?php
namespace App\Clients;

use Seriti\Tools\Table;

class ClientPayment extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'payment','col_label'=>'amount','pop_up'=>true];
        parent::setup($param);       

        //NB: master_col_idX shoudl be replaced by suitable master table cols you wish to use
        $this->setupMaster(array('table'=>TABLE_PREFIX.'client','key'=>'client_id','child_col'=>'client_id', 
                                'show_sql'=>'SELECT CONCAT("Client:",`name`) FROM `'.TABLE_PREFIX.'client` WHERE `client_id` = "{KEY_VAL}" '));  

        $this->addTableCol(array('id'=>'payment_id','type'=>'INTEGER','title'=>'Payment ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'date','type'=>'DATE','title'=>'Date','new'=>date('Y-m-d')));
        $this->addTableCol(array('id'=>'amount','type'=>'DECIMAL','title'=>'Amount'));
        $this->addTableCol(array('id'=>'description','type'=>'STRING','title'=>'Comment','required'=>false));
        $this->addTableCol(array('id'=>'transact_id','type'=>'INTEGER','title'=>'Ledger Transaction','edit'=>false,'required'=>false));

        $this->addSortOrder('T.`date` DESC,T.`amount` DESC ','Payment date latest, then highest amount','DEFAULT');

        $this->addAction(array('type'=>'edit','text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));

        $this->addSearch(array('date','amount','description','transact_id'),array('rows'=>2));

    }  
}