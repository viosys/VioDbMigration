# VioDbMigration

Shopware plugin to apply project-specific DB-Updates

## Setup

1. add composer dependency 
```json
{
    "require": {
        "viosys/vio-db-migration": "~1",
        ...
    },    
    ...
    "repositories": [
        {
            "type": "git",
            "url": "ssh://gitssh@80.153.5.86:30122/tfs/VIOSYS/Shopware%20Plugins/_git/VioDbMigration"
        }
    ]
}
```
2. create empty directory  `vio_sql/migrations`
3. create Migration-Class mit fortlaufender Nummer im Klassennamen und im Dateinamen  `100-migration.php`
```php
<?php

class Migrations_Migration100 extends \Shopware\Components\Migrations\VioAbstractMigration
{
    public function up($modus)
    {
        $sql = "INSERT INTO ...";
        $this->addsql($sql);
    }

}
```
