<?php 
namespace App\Clients;

use Seriti\Tools\Upload;

class CreditFile extends Upload 
{
  //configure
    public function setup($param = []) 
    {
        $id_prefix = 'CRD'; 

        $param = ['row_name'=>'Credit Note document',
                  'pop_up'=>true,
                  'update_calling_page'=>true,
                  'prefix'=>$id_prefix,//will prefix file_name if used, but file_id.ext is unique 
                  'upload_location'=>$id_prefix]; 
        parent::setup($param);

        $param=[];
        $param['table']     = TABLE_PREFIX.'credit';
        $param['key']       = 'credit_id';
        $param['label']     = 'credit_no';
        $param['child_col'] = 'location_id';
        $param['child_prefix'] = $id_prefix;
        $param['show_sql'] = 'SELECT CONCAT("Invoice: ",`credit_no`) FROM `'.TABLE_PREFIX.'credit` WHERE `credit_id` = "{KEY_VAL}"';
        $this->setupMaster($param);

        $this->addAction('delete');

        $access['read_only'] = true;                         
        $this->modifyAccess($access);
    }
}
?>
