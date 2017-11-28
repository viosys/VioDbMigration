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

    protected static $tableName =  'vio_db_migration_schema_version';

    public function __construct(\PDO $connection, $migrationPath, Container $container)
    {
        parent::__construct($connection, $migrationPath);
        $this->container = $container;
        $this->createSchemaTable();
    }

    public function createSchemaTable()
    {
        $sql = '
            CREATE TABLE IF NOT EXISTS `' . static::$tableName . '` (
            `version` int(11) NOT NULL,
            `start_date` datetime NOT NULL,
            `complete_date` datetime DEFAULT NULL,
            `name` VARCHAR( 255 ) NOT NULL,
            `error_msg` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
        ';
        $this->connection->exec($sql);
        // migrate from version.txt
        $txtVersion = intval($this->getCurrentVersionFromVersionTxt());
        if($txtVersion > 0) {
            $sql = "INSERT INTO " . static::$tableName . " (version, start_date, complete_date, name)
                      VALUES ({$txtVersion}, NOW(), NOW(), 'Version from old version.txt') ";
            $this->connection->exec($sql);
            $this->deleteVersionTxt();
        }
    }

    private function getCurrentVersionFromVersionTxt()
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

    private function deleteVersionTxt()
    {
        $path = $this->getMigrationPath();
        $path .= DIRECTORY_SEPARATOR;
        $path .= 'version.txt';
        unlink($path);
    }


    /**
     * Returns current schma version found in database
     *
     * @return int
     */
    public function getCurrentVersion()
    {
        $sql = 'SELECT version FROM ' . static::$tableName . ' WHERE complete_date IS NOT NULL ORDER BY version DESC';
        $currentVersion = (int) $this->connection->query($sql)->fetchColumn();

        return $currentVersion;
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
        $sql = 'REPLACE ' . static::$tableName . ' (version, start_date, name) VALUES (:version, :date, :name)';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            ':version' => $migration->getVersion(),
            ':date' => date('Y-m-d H:i:s'),
            ':name' => $migration->getLabel(),
        ]);

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
            $updateVersionSql = 'UPDATE ' . static::$tableName . ' SET error_msg = :msg WHERE version = :version';
            $stmt = $this->connection->prepare($updateVersionSql);
            $stmt->execute([
                ':version' => $migration->getVersion(),
                ':msg' => $e->getMessage(),
            ]);
            throw new \Exception(sprintf(
                'Could not apply migration (%s). Error: %s ', get_class($migration), $e->getMessage()
            ));
        }
        $sql = 'UPDATE ' . static::$tableName . ' SET complete_date = :date WHERE version = :version';
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            ':version' => $migration->getVersion(),
            ':date' => date('Y-m-d H:i:s'),
        ]);
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
            $migrationNamespace = $this->container->get('config')->getByNamespace('VioDbMigration', 'VioMigrationNamespace');
            $migrationClassName = $migrationNamespace . '\Migrations_Migration' . $result['1'];
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