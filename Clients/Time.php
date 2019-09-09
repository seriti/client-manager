<?php 
namespace App\Clients;

use Seriti\Tools\Table;

class Time extends Table 
{

    //configure
    public function setup() 
    {
        $param = ['row_name'=>'Time sheet','col_label'=>'comment'];
        parent::setup($param);

        //define('TABLE_PREFIX',$module['table_prefix']);      

        $this->addTablecol(array('id'=>'time_id','type'=>'INTEGER','title'=>'ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTablecol(array('id'=>'client_id','type'=>'INTEGER','title'=>'Client','join'=>'name FROM '.TABLE_PREFIX.'client WHERE client_id'));
        $this->addTablecol(array('id'=>'user_id','type'=>'INTEGER','title'=>'User','join'=>'name FROM user_admin WHERE user_id'));
        $this->addTablecol(array('id'=>'time_start','type'=>'DATETIME','title'=>'Start date'));
        $this->addTablecol(array('id'=>'time_minutes','type'=>'INTEGER','title'=>'Time(minutes)'));
        $this->addTablecol(array('id'=>'comment','type'=>'TEXT','title'=>'Work done'));
        $this->addTablecol(array('id'=>'type_id','type'=>'INTEGER','title'=>'Work type','join'=>'name FROM '.TABLE_PREFIX.'time_type WHERE type_id'));

        $this->addSortOrder('T.time_id DESC','Create date latest','DEFAULT');

        $this->addAction(array('type'=>'edit','text'=>'edit'));
        //$this->addAction(array('type'=>'view','text'=>'view'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));

        $this->addSearch(array('client_id','user_id','time_start','time_minutes','comment','type_id'),array('rows'=>2));

        $this->addSelect('client_id','SELECT client_id,name FROM '.TABLE_PREFIX.'client ORDER BY name');
        $this->addSelect('type_id','SELECT type_id,name FROM '.TABLE_PREFIX.'time_type ORDER BY name');
        $this->addSelect('user_id','SELECT user_id,name FROM user_admin ORDER BY name');
        //$this->addSelect('status','(SELECT "OK") UNION (SELECT "HIDE")');
    }

}    
?>
