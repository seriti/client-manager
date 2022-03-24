<?php
namespace App\Clients;

use Seriti\Tools\SetupModuleData;

class SetupData extends SetupModuledata
{

    public function setupSql()
    {
        $this->tables = ['client','credit','credit_data','invoice','invoice_data','invoice_fixed','time','time_type','user_extend','payment','files','task','task_diary'];

        $this->addCreateSql('client',
                            'CREATE TABLE `TABLE_NAME` (
                                `client_id` int(11) NOT NULL AUTO_INCREMENT,
                                `name` varchar(64) NOT NULL,
                                `email` varchar(64) NOT NULL,
                                `status` varchar(16) NOT NULL,
                                `invoice_no` int(11) NOT NULL,
                                `credit_no` int(11) NOT NULL,
                                `contact_name` varchar(64) NOT NULL,
                                `invoice_prefix` varchar(8) NOT NULL,
                                `email_alt` varchar(64) NOT NULL,
                                `date_statement_start` DATE NOT NULL,
                                `keywords` TEXT NOT NULL,
                                PRIMARY KEY (`client_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8'); 

        $this->addCreateSql('credit',
                            'CREATE TABLE `TABLE_NAME` (
                                `credit_id` int(11) NOT NULL AUTO_INCREMENT,
                                `client_id` int(11) NOT NULL,
                                `credit_no` varchar(16) NOT NULL,
                                `amount` double NOT NULL,
                                `vat` double NOT NULL,
                                `total` double NOT NULL,
                                `date` date NOT NULL,
                                `comment` text NOT NULL,
                                `status` varchar(16) NOT NULL,
                                `doc_name` varchar(255) NOT NULL,
                                
                                PRIMARY KEY (`credit_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');  

        $this->addCreateSql('credit_data',
                            'CREATE TABLE `TABLE_NAME` (
                                `data_id` int(11) NOT NULL AUTO_INCREMENT,
                                `credit_id` int(11) NOT NULL,
                                `item` varchar(250) NOT NULL,
                                `quantity` decimal(12,2) NOT NULL,
                                `price` decimal(12,2) NOT NULL,
                                `total` decimal(12,2) NOT NULL,
                                PRIMARY KEY (`data_id`)
                            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8');

        $this->addCreateSql('invoice',
                            'CREATE TABLE `TABLE_NAME` (
                                `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
                                `client_id` int(11) NOT NULL,
                                `invoice_no` varchar(16) NOT NULL,
                                `amount` double NOT NULL,
                                `vat` double NOT NULL,
                                `total` double NOT NULL,
                                `date` date NOT NULL,
                                `comment` text NOT NULL,
                                `status` varchar(16) NOT NULL,
                                `doc_name` varchar(255) NOT NULL,
                                PRIMARY KEY (`invoice_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');  

        $this->addCreateSql('invoice_data',
                            'CREATE TABLE `TABLE_NAME` (
                                `data_id` int(11) NOT NULL AUTO_INCREMENT,
                                `invoice_id` int(11) NOT NULL,
                                `item` varchar(250) NOT NULL,
                                `quantity` decimal(12,2) NOT NULL,
                                `price` decimal(12,2) NOT NULL,
                                `total` decimal(12,2) NOT NULL,
                                PRIMARY KEY (`data_id`)
                            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8');

        $this->addCreateSql('invoice_fixed',
                            'CREATE TABLE `TABLE_NAME` (
                                `name` varchar(250) NOT NULL,
                                `quantity` int(11) NOT NULL,
                                `price` decimal(12,2) NOT NULL,
                                `repeat_period` varchar(16) NOT NULL,
                                `repeat_date` date NOT NULL,
                                `fixed_id` int(11) NOT NULL AUTO_INCREMENT,
                                `client_id` int(11) NOT NULL,
                                PRIMARY KEY (`fixed_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');  

        $this->addCreateSql('time',
                            'CREATE TABLE `TABLE_NAME` (
                                `time_id` int(10) NOT NULL AUTO_INCREMENT,
                                `time_start` datetime NOT NULL,
                                `time_minutes` double NOT NULL,
                                `comment` text NOT NULL,
                                `client_id` int(11) NOT NULL,
                                `type_id` int(11) NOT NULL,
                                `user_id` int(11) NOT NULL,
                                PRIMARY KEY (`time_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');  

        $this->addCreateSql('time_type',
                            'CREATE TABLE `TABLE_NAME` (
                                `activity_id` varchar(16) NOT NULL,
                                `name` varchar(255) NOT NULL,
                                `time_penalty` double NOT NULL,
                                `status` varchar(16) NOT NULL,
                                `type_id` int(11) NOT NULL AUTO_INCREMENT,
                                PRIMARY KEY (`type_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');  

        $this->addCreateSql('user_extend',
                            'CREATE TABLE `TABLE_NAME` (
                                `extend_id` int(11) NOT NULL AUTO_INCREMENT,
                                `user_id` int(11) NOT NULL,
                                `parameter` varchar(64) NOT NULL,
                                `value` varchar(155) NOT NULL,
                                PRIMARY KEY (`extend_id`),
                                UNIQUE KEY `idx_user_extend1` (`user_id`,`parameter`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');  

        $this->addCreateSql('payment',
                            'CREATE TABLE `TABLE_NAME` (
                                `payment_id` INT NOT NULL AUTO_INCREMENT ,
                                `client_id` INT NOT NULL ,
                                `date` DATE NOT NULL ,
                                `amount` DECIMAL(12,2) NOT NULL ,
                                `description` VARCHAR(255) NOT NULL ,
                                `transact_id` INT NOT NULL ,
                                PRIMARY KEY (`payment_id`) 
                            ) ENGINE = MyISAM DEFAULT CHARACTER SET = utf8;');  

        $this->addCreateSql('files',
                            'CREATE TABLE `TABLE_NAME` (
                                `file_id` int(10) unsigned NOT NULL,
                                `title` varchar(255) NOT NULL,
                                `file_name` varchar(255) NOT NULL,
                                `file_name_orig` varchar(255) NOT NULL,
                                `file_text` longtext NOT NULL,
                                `file_date` date NOT NULL,
                                `location_id` varchar(64) NOT NULL,
                                `location_rank` int(11) NOT NULL,
                                `key_words` text NOT NULL,
                                `description` text NOT NULL,
                                `file_size` int(11) NOT NULL,
                                `encrypted` tinyint(1) NOT NULL,
                                `file_name_tn` varchar(255) NOT NULL,
                                `file_ext` varchar(16) NOT NULL,
                                `file_type` varchar(16) NOT NULL,
                                PRIMARY KEY (`file_id`),
                                KEY `search_idx2` (`location_id`),
                                FULLTEXT KEY `search_idx` (`key_words`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');  

        $this->addCreateSql('task',
                            'CREATE TABLE `TABLE_NAME` (
                                `task_id` int(11) NOT NULL AUTO_INCREMENT,
                                `name` varchar(64) NOT NULL,
                                `description` text NOT NULL,
                                `status` varchar(64) NOT NULL,
                                `date_create` date NOT NULL,
                                `client_id` int(11) NOT NULL,
                                PRIMARY KEY (`task_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');  

        $this->addCreateSql('task_diary',
                            'CREATE TABLE `TABLE_NAME` (
                                `diary_id` int(11) NOT NULL AUTO_INCREMENT,
                                `task_id` int(11) NOT NULL,
                                `date` datetime NOT NULL,
                                `notes` text NOT NULL,
                                `subject` varchar(255) NOT NULL,
                                PRIMARY KEY (`diary_id`)
                            ) ENGINE=MyISAM DEFAULT CHARSET=utf8');  

        //initialisation
        $this->addInitialSql('INSERT INTO `TABLE_PREFIXclient` (`name`,`email`,`status`,`invoice_no`,`invoice_prefix`) '.
                             'VALUES("My first client","first@client.com","OK",0,"INV")','created my first client');
        $this->addInitialSql('INSERT INTO `TABLE_PREFIXtime_type` (`name`,`time_penalty`,`status`) '.
                             'VALUES("Programming",0,"OK"),("Miscellaneous",0,"OK"),("Billable meeting",0,"OK"),("Non Billable meeting",0,"NOBILL"),
                                    ("Non Billable phone/internet call",0,"NOBILL"),("Billable meeting",0,"OK"),("Writing documentation",0,"OK"),
                                    ("Writing emails",0,"OK"),("Billable meeting",0,"OK"),("Billable phone/internet call",15,"OK")','Created timekeeping types');

        //updates use time stamp in ['YYYY-MM-DD HH:MM'] format, must be unique and sequential
        //$this->addUpdateSql('YYYY-MM-DD HH:MM','Update TABLE_PREFIX--- SET --- "X"');
        $this->addUpdateSql('2019-01-16 00:00','INSERT INTO `TABLE_PREFIXtask` (`name`,`description`,`status`,`date_create`,`client_id`) VALUES("wtf","wtf desc","OK","2018-12-12",63)');
        $this->addUpdateSql('2019-01-16 00:02','DELETE FROM `TABLE_PREFIXtask` WHERE `name` = "wtf"');
        $this->addUpdateSql('2019-01-16 00:03','INSERT INTO `TABLE_PREFIXtask` (`name`,`description`,`status`,`date_create`,`client_id`) VALUES("wtf","wtf desc","OK","2018-12-12",63)');
        $this->addUpdateSql('2019-01-16 00:04','DELETE FROM `TABLE_PREFIXtask` WHERE `name` = "wtf"','REMOVE "wtf" tasks');
        $this->addUpdateSql('2022-03-22 12:00','ALTER TABLE `TABLE_PREFIXclient` ADD COLUMN `credit_no` INT NOT NULL AFTER `invoice_no`');

        

    }
    
    
}


  
?>
