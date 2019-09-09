<?php
namespace App\Clients;

use Seriti\Tools\Dashboard AS DashboardTool;

class Dashboard extends DashboardTool
{
     

    //configure
    public function setup($param = []) 
    {
        $this->col_count = 2;  

        $login_user = $this->getContainer('user'); 
        //$login_user = $this->container->user; 

        //(block_id,col,row,title)
        $this->addBlock('TIME',1,1,'Time keeping');
        $this->addItem('TIME','Time sheet-1',['link'=>"javascript:open_popup('timesheet',400,500)"]);
        $this->addItem('TIME','Time sheet-2',['link'=>"javascript:open_popup2('timesheet',400,500)"]);

        $this->addBlock('WIZARD',2,1,'Process wizards');
        $this->addItem('WIZARD','Invoice wizard',['link'=>'invoice_wizard']);
        $this->addItem('WIZARD','Match Ledger payments to clients',['link'=>'payment_wizard']);
        $this->addItem('WIZARD','Update Invoice status and match payments',['link'=>'Invoice_payment_wizard']);
             
        $this->addBlock('SETUP',1,2,'Setup');
        $this->addItem('SETUP','Time keeping types',['link'=>'timetype']);
        $this->addItem('SETUP','System User settings',['link'=>'user_extend']);
        
        
        if($login_user->getAccessLevel() === 'GOD') {
            $this->addBlock('CONFIG',1,3,'Module Configuration');
            $this->addItem('CONFIG','Setup Database',['link'=>'setup_data','icon'=>'setup']);
            $this->addItem('CONFIG','Setup Invoices',['link'=>'invoice_setup','icon'=>'setup']);
        } 
        
        //die('WTF');

    }

}

?>