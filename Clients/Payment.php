<?php 
namespace App\Clients;

use Seriti\Tools\Table;

class Payment extends Table 
{
    //configure
    public function setup() 
    {
        $param = ['row_name'=>'Payment','col_label'=>'amount'];
        parent::setup($param);

        $this->addTableCol(array('id'=>'payment_id','type'=>'INTEGER','title'=>'Payment ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'client_id','type'=>'INTEGER','title'=>'Client','join'=>'name FROM '.TABLE_PREFIX.'client WHERE client_id'));
        $this->addTableCol(array('id'=>'date','type'=>'DATE','title'=>'Date','new'=>date('Y-m-d')));
        $this->addTableCol(array('id'=>'amount','type'=>'DECIMAL','title'=>'Amount'));
        $this->addTableCol(array('id'=>'description','type'=>'STRING','title'=>'Comment','required'=>false));
        $this->addTableCol(array('id'=>'transact_id','type'=>'INTEGER','title'=>'Ledger Transaction ID','required'=>false));

        $this->addSortOrder('T.date DESC,T.amount DESC ','Payment date latest, then highest amount','DEFAULT');

        $this->addAction(array('type'=>'edit','text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));

        $this->addSearch(array('client_id','date','amount','description','transact_id'),array('rows'=>2));

        $this->addSelect('client_id','SELECT client_id,name FROM '.TABLE_PREFIX.'client ORDER BY name');
    }    
}
?>
