<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;
use App\Clients\Task;

class TaskController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'task'; 
        $table = new Task($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All Tasks';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}