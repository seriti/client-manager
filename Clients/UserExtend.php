<?php 
namespace App\Clients;

use Seriti\Tools\Table;
use Seriti\Tools\TABLE_USER;

class UserExtend extends Table 
{
    protected function beforeUpdate($id,$edit_type,&$form,&$error_str) {
        if($form['parameter'] === 'HOURLY_RATE') {
          if(!is_numeric($form['value'])) $error_str .= 'Hourly rate not a valid number!';
        }  
    } 
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Setting','col_label'=>'parameter'];
        parent::setup($param);        

        $this->addTableCol(array('id'=>'extend_id','type'=>'INTEGER','title'=>'Extend ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'user_id','type'=>'INTEGER','title'=>'User','join'=>'CONCAT(name,": ",email) FROM '.TABLE_USER.' WHERE user_id'));
        $this->addTableCol(array('id'=>'parameter','type'=>'STRING','title'=>'Parameter'));
        $this->addTableCol(array('id'=>'value','type'=>'STRING','title'=>'Value'));

        $this->addAction(array('type'=>'edit','text'=>'edit'));
        $this->addAction(array('type'=>'view','text'=>'view'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));

        $this->addSearch(array('user_id','parameter','value'),array('rows'=>1));

        $this->addSelect('parameter','(SELECT "HOURLY_RATE")');
        $this->addSelect('user_id','SELECT user_id,name FROM '.TABLE_USER.' WHERE status = "OK"');
    }    

}
?>
