<?php

namespace VioDbMigration\Commands;

use VioDbMigration\Components\Migrations\VioManager;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopware\Commands\ShopwareCommand;

class DbMigration extends ShopwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('vio:migrations:migrate');

        $this->addOption(
            'mode',
            null,
            InputOption::VALUE_REQUIRED,
            "Mode to run: Install or Update",
            'update'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->container->get('loader')->registerNamespace(
            'Shopware\Components',
            __DIR__ . '/../Components/'
        );

        $connection = $this->getContainer()->get('db_connection');
        $rootDir = $this->getContainer()->getParameter('kernel.root_dir');
        $baseDir =  $this->getContainer()->hasParameter('shopware.app.rootdir') ? $this->getContainer()->getParameter('shopware.app.rootdir') : $rootDir;

        $mode = $input->getOption('mode');
        $migrationPath = $this->getContainer()->get('config')->getByNamespace('VioDbMigration', 'VioMigrationPath');
        $migrationPath = ltrim($migrationPath, '/');
        $migrationManger = new VioManager($connection, $baseDir . '/' . $migrationPath, $this->getContainer());
        $migrationManger->run($mode);
    }
}
