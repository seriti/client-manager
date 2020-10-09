<?php 
namespace App\Clients;

use Seriti\Tools\Upload;

class TaskFile extends Upload 
{
  //configure
    public function setup($param = []) 
    {
        $id_prefix = 'TSK';

        $param = ['row_name'=>'Task document',
                  'pop_up'=>true,
                  'update_calling_page'=>true,
                  'prefix'=>$id_prefix,//will prefix file_name if used, but file_id.ext is unique 
                  'upload_location'=>$id_prefix]; 
        parent::setup($param);

        $param=[];
        $param['table']     = TABLE_PREFIX.'task';
        $param['key']       = 'task_id';
        $param['label']     = 'name';
        $param['child_col'] = 'location_id';
        $param['child_prefix'] = $id_prefix;
        $param['show_sql'] = 'SELECT CONCAT("Task: ",name) FROM '.TABLE_PREFIX.'task WHERE task_id = "{KEY_VAL}"';
        $this->setupMaster($param);

        $this->addAction('delete');

        //$access['read_only'] = true;                         
        //$this->modifyAccess($access); 
    }
}
?>
