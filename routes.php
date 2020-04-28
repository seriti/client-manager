<?php  
/*
NB: This is not stand alone code and is intended to be used within "seriti/slim3-skeleton" framework
The code snippet below is for use within an existing src/routes.php file within this framework
copy the "/client" group into the existing "/admin" group within existing "src/routes.php" file 
*/

$app->group('/admin', function () {

    $this->group('/client', function () {
        $this->any('/dashboard', \App\Clients\DashboardController::class);
        $this->any('/client', \App\Clients\ClientController::class);
        $this->any('/client_fixed', \App\Clients\ClientFixedController::class);
        $this->any('/client_payment', \App\Clients\ClientPaymentController::class);
        $this->any('/payment', \App\Clients\PaymentController::class);
        $this->any('/payment_wizard', \App\Clients\PaymentWizardController::class);
        $this->any('/task', \App\Clients\TaskController::class);
        $this->any('/task_diary', \App\Clients\TaskDiaryController::class);
        $this->any('/task_file', \App\Clients\TaskFileController::class);
        $this->any('/time', \App\Clients\TimeController::class);
        $this->any('/timesheet', \App\Clients\TimeSheetController::class);
        $this->any('/timetype', \App\Clients\TimeTypeController::class);
        $this->any('/invoice', \App\Clients\InvoiceController::class);
        $this->any('/invoice_file', \App\Clients\InvoiceFileController::class);
        $this->any('/invoice_item', \App\Clients\InvoiceItemController::class);
        $this->any('/invoice_setup', \App\Clients\InvoiceSetupController::class);
        $this->any('/invoice_wizard', \App\Clients\InvoiceWizardController::class);
        $this->any('/invoice_payment_wizard', \App\Clients\InvoicePaymentWizardController::class);
        $this->any('/user_extend', \App\Clients\UserExtendController::class);
        $this->any('/report', \App\Clients\ReportController::class);
        $this->get('/setup_data', \App\Clients\SetupDataController::class);
    })->add(\App\Clients\Config::class);


})->add(\App\User\ConfigAdmin::class);



