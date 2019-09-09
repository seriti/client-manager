<?php 
namespace App\Clients;

use Seriti\Tools\Table;

class TimeType extends Table 
{

    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Type','col_label'=>'name'];
        parent::setup($param);        

        $this->addForeignKey(array('table'=>TABLE_PREFIX.'time','col_id'=>'type_id','message'=>'A timesheet exists for this type'));  

        $this->addTableCol(array('id'=>'type_id','type'=>'INTEGER','title'=>'Type ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Name'));
        $this->addTableCol(array('id'=>'time_penalty','type'=>'STRING','title'=>'Time penalty(minutes)'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));

        $this->addAction(array('type'=>'edit','text'=>'edit'));
        $this->addAction(array('type'=>'view','text'=>'view'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));

        $this->addSelect('status','(SELECT "OK") UNION (SELECT "NOBILL") UNION (SELECT "HIDE")');
    }
}
?>
