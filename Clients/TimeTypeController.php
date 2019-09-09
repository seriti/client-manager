<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;
use App\Clients\TimeType;

class TimeTypeController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'time_type'; 
        $table = new TimeType($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All Time sheet types';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}