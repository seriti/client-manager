<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;
use App\Clients\TaskDiary;

class TaskDiaryController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'task_diary'; 
        $table = new TaskDiary($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'Task diary';
        
        return $this->container->view->render($response,'admin_popup.php',$template);
    }
}