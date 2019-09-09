<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;
use App\Clients\Time;

class TimeController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'time'; 
        $table = new Time($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All Timesheets';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}