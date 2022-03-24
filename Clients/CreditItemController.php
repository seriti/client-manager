<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;
use App\Clients\CreditItem;

class CreditItemController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'credit_data'; 
        $table = new CreditItem($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All Credit Note Items';
        
        return $this->container->view->render($response,'admin_popup.php',$template);
    }
}