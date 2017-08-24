<?php
/**
 * Created by PhpStorm.
 * User: Sebastian König
 * Date: 03.03.2017
 * Time: 11:18
 */

namespace VioDbMigration\Components\Migrations;

use Shopware\Components\DependencyInjection\Container;
use Shopware\Components\Migrations\AbstractMigration;
use Shopware\Components\Migrations\Manager;

class VioManager extends Manager {

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

    /**
     * Return an array of Migrations that have a higher version than $currentVersion
     * The array is indexed by Version
     *
     * @param int $currentVersion
     * @param int $limit
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getMigrationsForVersion($currentVersion, $limit = null)
    {
        $regexPattern = '/^([0-9]*)-.+\.php$/i';

        $migrationPath = $this->getMigrationPath();

        $directoryIterator = new \DirectoryIterator($migrationPath);
        $regex = new \RegexIterator($directoryIterator, $regexPattern, \RecursiveRegexIterator::GET_MATCH);

        $migrations = [];

        foreach ($regex as $result) {
            $migrationVersion = $result['1'];
            if ($migrationVersion <= $currentVersion) {
                continue;
            }

            $migrationClassName = 'VioDbMigration\Migrations_Migration' . $result['1'];
            if (!class_exists($migrationClassName, false)) {
                $file = $migrationPath . '/' . $result['0'];
                require $file;
            }

            try {
                /** @var $migrationClass AbstractMigration */
                $migrationClass = new $migrationClassName($this->getConnection());
            } catch (\Exception $e) {
                throw new \Exception('Could not instantiate Object');
            }

            if (!($migrationClass instanceof AbstractMigration)) {
                throw new \Exception("$migrationClassName is not instanceof AbstractMigration");
            }

            if ($migrationClass->getVersion() != $result['0']) {
                throw new \Exception(
                    sprintf('Version mismatch. Version in filename: %s, Version in Class: %s', $result['1'], $migrationClass->getVersion())
                );
            }

            $migrations[$migrationClass->getVersion()] = $migrationClass;
        }

        ksort($migrations);

        if ($limit !== null) {
            return array_slice($migrations, 0, $limit, true);
        }

        return $migrations;
    }
}