<?php
/**
 * Created by PhpStorm.
 * User: Sebastian König
 * Date: 03.03.2017
 * Time: 11:18
 */

namespace Shopware\Components\Migrations;

use Shopware\Components\DependencyInjection\Container;

class VioManager extends Manager{

    /** @var  Container $container */
    protected $container;

    public function __construct(\PDO $connection, $migrationPath, Container $container)
    {
        parent::__construct($connection, $migrationPath);
        $this->container = $container;
    }

    public function createSchemaTable()
    {

    }

    public function getCurrentVersion()
    {
        $path = $this->getMigrationPath();
        $path .= DIRECTORY_SEPARATOR;
        $path .= 'version.txt';

        if(!file_exists($path))
            fclose(fopen($path, 'w'));

        $version = file_get_contents($path);
        $version = intval($version);
        return $version;
    }

    public function setCurrentVersion($version)
    {
        $path = $this->getMigrationPath();
        $path .= DIRECTORY_SEPARATOR;
        $path .= 'version.txt';

        file_put_contents($path, $version);
    }

    /**
     * Applies given $migration to database
     *
     * @param AbstractMigration $migration
     * @param string $modus
     * @throws \Exception
     */
    public function apply(AbstractMigration $migration, $modus = AbstractMigration::MODUS_INSTALL)
    {
        $oldVersion = $this->getCurrentVersion();
        $this->setCurrentVersion($migration->getVersion());

        try {
            if($migration instanceof VioAbstractMigration){
                $migration->setContainer($this->container);
            }

            $migration->up($modus);
            $sqls = $migration->getSql();

            foreach ($sqls as $sql) {
                $this->connection->exec($sql);
            }
        } catch (\Exception $e) {

            $this->setCurrentVersion($oldVersion);
            throw new \Exception(sprintf(
                'Could not apply migration (%s). Error: %s ', get_class($migration), $e->getMessage()
            ));
        }

    }
}