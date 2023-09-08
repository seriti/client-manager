<?php 
namespace App\Clients;

use Seriti\Tools\Table;

class Client extends Table 
{

    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Client','col_label'=>'name'];
        parent::setup($param);        

        $this->addTableCol(array('id'=>'client_id','type'=>'INTEGER','title'=>'Client ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Client name'));
        $this->addTableCol(array('id'=>'description','type'=>'TEXT','title'=>'Client detail','required'=>false,'hint'=>'This will appear on invoice beneath name if specified'));
        $this->addTableCol(array('id'=>'contact_name','type'=>'STRING','title'=>'Contact person'));
        $this->addTableCol(array('id'=>'email','type'=>'EMAIL','title'=>'Email primary'));
        $this->addTableCol(array('id'=>'email_alt','type'=>'EMAIL','title'=>'Email alternative','required'=>false));
        $this->addTableCol(array('id'=>'invoice_prefix','type'=>'STRING','title'=>'Invoice prefix','required'=>false));
        $this->addTableCol(array('id'=>'Invoice_no','type'=>'INTEGER','title'=>'Invoice count','required'=>false));
        $this->addTableCol(array('id'=>'date_statement_start','type'=>'DATE','title'=>'Statement start date','new'=>date('Y-m-d')));
        $this->addTableCol(array('id'=>'keywords','type'=>'TEXT','title'=>'Key words','list'=>false,'required'=>false,'hint'=>'(Used to match payment transaction to client)'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status','new'=>'ACTIVE'));

        $this->addAction(array('type'=>'edit','text'=>'edit'));
        //$this->addAction(array('type'=>'view','text'=>'view'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));

        $this->addAction(array('type'=>'popup','text'=>'invoices','url'=>'client_fixed','mode'=>'view','width'=>600,'height'=>600)); 
        $this->addAction(array('type'=>'popup','text'=>'payments','url'=>'client_payment','mode'=>'view','width'=>600,'height'=>600)); 

        $this->addSearch(array('name','description','contact_name','email','invoice_prefix','status'),array('rows'=>2));

        $this->addSelect('status','(SELECT "ACTIVE") UNION (SELECT "INACTIVE")');
    }
}
?>