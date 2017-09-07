<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace VioDbMigration\Commands;

use VioDbMigration\Components\Migrations\VioManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VioDbMigration\Components\Migrations\VioDependencyManager;

class MigrationsMigrateCommand extends \Shopware\Commands\MigrationsMigrateCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('sw:migrations:migrate');

        $this->addOption(
            'mode',
            null,
            InputOption::VALUE_REQUIRED,
            'Mode to run: Install or Update',
            'update'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = $this->getContainer()->get('db_connection');
        $rootDir = $this->getContainer()->getParameter('kernel.root_dir');

        $mode = $input->getOption('mode');

        $migrationPath = $this->getContainer()->get('config')->getByNamespace('VioDbMigration', 'VioMigrationPath');
        $migrationPath = ltrim($migrationPath, '/');

        $migrationManger = new VioDependencyManager(
            $connection,
            $rootDir . '/_sql/migrations',
             new VioManager(
                 $connection,
                 $rootDir . '/' . $migrationPath,
                 $this->getContainer()
             )
            );
        $migrationManger->run($mode);
    }
}
