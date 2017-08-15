<?php

namespace VioDbMigration;

use Shopware\Components\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Shopware-Plugin VioDbMigration.
 */
class VioDbMigration extends Plugin
{

    /**
    * @param ContainerBuilder $container
    */
    public function build(ContainerBuilder $container)
    {
        $container->setParameter('vio_db_migration.plugin_dir', $this->getPath());
        parent::build($container);
    }

}
