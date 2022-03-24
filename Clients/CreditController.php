<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;
use App\Clients\Credit;

class CreditController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'credit'; 
        $table = new Credit($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All Credit notes';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}