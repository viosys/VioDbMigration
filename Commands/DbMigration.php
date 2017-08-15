<?php

namespace VioDbMigration\Commands;

use Shopware\Components\Migrations\VioManager;
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

        $mode = $input->getOption('mode');

        $migrationManger = new VioManager($connection, $rootDir . '/vio_sql/migrations', $this->getContainer());
        $migrationManger->run($mode);
    }
}
