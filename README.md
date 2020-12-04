# VioDbMigration

Shopware plugin to apply project-specific DB-Updates

## Setup

### Git Version
* Checkout Plugin in `/custom/plugins/VioDbMigration`
* Change to Directory and run `composer install` to install the dependencies
* Install the Plugin with the Plugin Manager

### Install with composer
* Change to your root Installation of shopware
* Run command `composer require viosys/vio-db-migration` and install and active plugin with Plugin Manager 

### Install and active the plugin
* to install the plugin via build process, we have added an [installation script](deploy.php)
* which you could run in your ant-build or the composer post-update script

1. Composer
    - modify your repository version of `app/post-update.sh`
    - add the following line before the migration console command
    ```shell script
    /usr/bin/env php $(dirname "$0")/../custom/project/VioDbMigration/deploy.php --dbhost ${DB_HOST} --dbname ${DB_DATABASE} --dbuser ${DB_USERNAME} --dbpassword ${DB_PASSWORD} --migrationpath ${VIO_MIGRATIONPATH} --migrationnamespace ${VIO_MIGRATIONNAMESPACE}
    ```
2. Ant
    - when you want to run and add VioDbMigration in your ant-build definition send a message  

## Usage

1. create empty directory  `vio_sql/migrations`
2. create Migration-Class with a consecutive number in the class name and in the file name `100-migration.php`

```php
<?php

class Migrations_Migration100 extends \VioDbMigration\Components\Migrations\VioAbstractMigration
{
    public function up($modus)
    {
        $sql = "INSERT INTO ...";
        $this->addsql($sql);
    }
}
```

## Features

### define depending showpare migration

To define a depending shopware migration you could overwride the `getDependendSwMigrationStep` method.
This will ensure, that the migration is executed directly after the defined migration,

```php
public function getDependendSwMigrationStep()
{
    return \Migrations_Migration920::class;
}
```

### use the di-container

Within a VioDbMigration you could use the di-container. In example to install a plugin.

```php
public function up($modus)
{
    $pm = $this->container->get('shopware_plugininstaller.plugin_manager');
    $pm->refreshPluginList();
    $VioVariantFilter = $pm->getPluginByName('FroshProfiler');
    if($VioVariantFilter){
        if($VioVariantFilter->getInstalled()){
            $pm->uninstallPlugin($VioVariantFilter);
        }
    }
}
```

### use predefined migration-scripts

There is a list of predefined method to executed common migrations:

1. `addThemeConfigElementSql` -> insert or update a theme config value
2. `setConfigValue` -> insert or update a shop/plugin config value
3. `addCoreSnippets` -> replace a snippet
4. `installPlugin` -> shorthand to install a plugin
4. `removePlugin` -> shorthand to clean delete a plugin
4. `insertPage` -> create shop page
4. `insertPageGroup` -> create a shop page group

