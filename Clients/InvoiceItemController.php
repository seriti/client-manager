<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;
use App\Clients\InvoiceItem;

class InvoiceItemController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'invoice_data'; 
        $table = new InvoiceItem($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All Invoice Items';
        
        return $this->container->view->render($response,'admin_popup.php',$template);
    }
}