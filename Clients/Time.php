<?php 
namespace App\Clients;

use Seriti\Tools\Table;

use App\Clients\ACCESS;

class Time extends Table 
{

    //ONLY show logged in user timesheets 
    protected $user_only = false;

    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Time sheet','col_label'=>'comment'];
        parent::setup($param);

        if($this->user_access_level !== 'GOD' and ACCESS['timesheets'] === 'USER') {
            $this->user_only = true;
        }

        //define('TABLE_PREFIX',$module['table_prefix']);      

        $this->addTablecol(array('id'=>'time_id','type'=>'INTEGER','title'=>'ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTablecol(array('id'=>'client_id','type'=>'INTEGER','title'=>'Client',
                                 'join'=>'`name` FROM `'.TABLE_PREFIX.'client` WHERE `client_id`'));
        if($this->user_only){
            $this->addColFixed(['id'=>'user_id','value'=>$this->user_id]);
        } else {
            $this->addTablecol(array('id'=>'user_id','type'=>'INTEGER','title'=>'User',
                                     'join'=>'`name` FROM `user_admin` WHERE `user_id`'));
        }
        
        $this->addTablecol(array('id'=>'time_start','type'=>'DATETIME','title'=>'Start date'));
        $this->addTablecol(array('id'=>'time_minutes','type'=>'INTEGER','title'=>'Time(minutes)'));
        $this->addTablecol(array('id'=>'comment','type'=>'TEXT','title'=>'Work done'));
        $this->addTablecol(array('id'=>'type_id','type'=>'INTEGER','title'=>'Work type',
                                 'join'=>'`name` FROM `'.TABLE_PREFIX.'time_type` WHERE `type_id`'));

        if($this->user_only) {
            $this->addSql('WHERE','T.`user_id` = "'.$this->user_id.'" ');   
        }

        $this->addSortOrder('T.time_id DESC','Create date latest','DEFAULT');

        $this->addAction(array('type'=>'edit','text'=>'edit'));
        //$this->addAction(array('type'=>'view','text'=>'view'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));

        $this->addSearch(array('client_id','user_id','time_start','time_minutes','comment','type_id'),array('rows'=>2));

        $this->addSelect('client_id','SELECT `client_id`,`name` FROM `'.TABLE_PREFIX.'client` ORDER BY `name`');
        $this->addSelect('type_id','SELECT `type_id`,`name` FROM `'.TABLE_PREFIX.'time_type` ORDER BY `name`');
        $this->addSelect('user_id','SELECT `user_id`,`name` FROM `user_admin` ORDER BY `name`');
        //$this->addSelect('status','(SELECT "OK") UNION (SELECT "HIDE")');
    }

}    
?>
