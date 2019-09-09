<?php
namespace App\Clients;

use Psr\Container\ContainerInterface;
use App\Clients\TimeSheet;

class TimeSheetController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $timesheet = new TimeSheet($this->container->mysql,$this->container);

        $html = $timesheet->process();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'Timesheet <a href="Javascript:onClick=window.close()">[close]</a>';
        $template['javascript'] = $timesheet->getJavascript();
        
        return $this->container->view->render($response,'admin_popup.php',$template);
    }
}