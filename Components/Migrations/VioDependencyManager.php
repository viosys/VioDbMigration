<?php
/**
 * Created by PhpStorm.
 * User: Sebastian König
 * Date: 16.08.2017
 * Time: 13:01
 */

namespace VioDbMigration\Components\Migrations;


use Shopware\Components\Migrations\AbstractMigration;
use Shopware\Components\Migrations\Manager;


class VioDependencyManager extends Manager
{
    /**
     * @var VioManager
     */
    private $vioManager;

    /**
     * @var VioAbstractMigration[]
     */
    private $vioMigrations;

    /** @var  array */
    private $migrationsStops;

    /**
     * VioDependencyManager constructor.
     * @param \PDO $connection
     * @param string $migrationPath
     * @param VioManager $vioManager
     */
    public function __construct(\PDO $connection, $migrationPath, VioManager $vioManager)
    {
        parent::__construct($connection, $migrationPath);
        $this->vioManager = $vioManager;
    }

    public function run($modus = AbstractMigration::MODUS_INSTALL)
    {
        $this->collectVioMigrationSteps();
        parent::run($modus);
        $this->vioManager->run($modus);
    }

    protected function collectVioMigrationSteps(){
        $this->vioMigrations = $this->vioManager->getMigrationsForVersion($this->vioManager->getCurrentVersion());
        $this->migrationsStops = [];
        foreach ($this->vioMigrations as $migration ) {
            if ($migration->getDependendSwMigrationStep()) {
                $this->migrationsStops[$migration->getDependendSwMigrationStep()] = $migration->getVersion();
            }
        }
    }

    public function apply(AbstractMigration $migration, $modus = AbstractMigration::MODUS_INSTALL)
    {
        $className = get_class($migration);

        parent::apply($migration, $modus);

        if( array_key_exists($className, $this->migrationsStops) )
        {
            $this->collectVioMigrationSteps();
            $viomigration = $this->vioManager->getNextMigrationForVersion($this->vioManager->getCurrentVersion());
            while ($viomigration->getVersion() <= $this->migrationsStops[$className] ){
                $this->log(sprintf('Apply VioMigrationNumber: %s - %s', $viomigration->getVersion(), $viomigration->getLabel()));
                $this->vioManager->apply($viomigration);
                $viomigration = $this->vioManager->getNextMigrationForVersion($this->vioManager->getCurrentVersion());
            }
        }


    }
}