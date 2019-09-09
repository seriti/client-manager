<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;
use App\Clients\Payment;

class PaymentController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'payment'; 
        $table = new Payment($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All Payments';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}