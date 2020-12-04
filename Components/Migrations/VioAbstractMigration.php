<?php
/**
 * Created by PhpStorm.
 * User: Sebastian König
 * Date: 03.03.2017
 * Time: 13:31
 */

namespace  VioDbMigration\Components\Migrations;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Components\Migrations\AbstractMigration;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

abstract class VioAbstractMigration extends AbstractMigration {

    /** @var  Container $container */
    protected $container;

    /**
     *
     * @return string|null
     */
    public function getDependendSwMigrationStep()
    {
        return null;
    }

    /**
     * @param string $themeNamePattern
     * @param string $elementName
     * @param mixed $elementValue
     */
    protected function addThemeConfigElementSql($themeNamePattern, $elementName, $elementValue)
    {
        $sql = "INSERT INTO s_core_templates_config_values (element_id, shop_id, `value`)
                  (
                    SELECT
                      sctce.id,
                      scs.id,
                      " . $this->connection->quote(serialize($elementValue)) . "
                    FROM s_core_shops scs
                      LEFT JOIN s_core_templates sct
                        ON scs.template_id = sct.id
                      LEFT JOIN s_core_templates_config_elements sctce
                        ON sct.id = sctce.template_id
                    WHERE sct.name LIKE " . $this->connection->quote($themeNamePattern) . "
                      AND sctce.name = " . $this->connection->quote($elementName) . "
                  )
                ON DUPLICATE KEY UPDATE
                  s_core_templates_config_values.value=" . $this->connection->quote(serialize($elementValue)) . ";";
        $this->addSql($sql);
    }

    protected function setConfigValue($elementName, $elementValue, $shopId = 0)
    {
        $shopClause = $shopId == 0 ? "" : " AND scs.id = " . $shopId;
        $sql = "INSERT INTO s_core_config_values (element_id, shop_id, value)
                  (
                      SELECT
                        scce.id, scs.id, " . $this->connection->quote(serialize($elementValue)) . "
                      FROM s_core_shops scs
                        INNER JOIN s_core_config_elements scce
                          ON scce.name = " . $this->connection->quote($elementName) . "
                " . $shopClause . "
                  )
                ON DUPLICATE KEY UPDATE
                  s_core_config_values.value=" . $this->connection->quote(serialize($elementValue)) . ";";
        $this->addSql($sql);
    }

    /**
     * updates Snippet - Textbaustein
     * @param string $value - snippet
     * @param string $namespace
     * @param string $name
     * @param string|int $shopId
     * @param string|int $localeId
     * @deprecated
     */
    protected function updateCoreSnippets($value, $namespace, $name, $shopId, $localeId){
        return $this->addCoreSnippets($value, $namespace, $name, $shopId, $localeId);
    }

    /**
     * Inserts a new text snippet
     * @param string $value - snippet
     * @param string $namespace
     * @param string $name
     * @param string|int $shopId
     * @param string|int $localeId
     */
    protected function addCoreSnippets($value, $namespace, $name, $shopId, $localeId){
        $sql = "REPLACE INTO s_core_snippets
         SET namespace = ".$this->connection->quote($namespace).",
         shopID = ".$this->connection->quote($shopId).",
         localeID =". $this->connection->quote($localeId).",
         name = ".$this->connection->quote($name).",
         value = ".$this->connection->quote($value).",
         dirty = 1,
         created = now(),
         updated = now()";

        $this->addSql($sql);
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;
        return $this;
    }

    public function installPlugin($pluginName, $forceInstall = false)
    {
        /** @var InstallerService $pm */
        $pm = $this->container->get('shopware_plugininstaller.plugin_manager');
        $pm->refreshPluginList();
        $plugin = $pm->getPluginByName($pluginName);
        if($plugin){
            if(!$plugin->getInstalled()){
                $pm->installPlugin($plugin);
            }
            elseif($forceInstall){
                $pm->uninstallPlugin($plugin);
                $pm->installPlugin($plugin);
            }
            if(version_compare($plugin->getVersion(), $plugin->getUpdateVersion(), '<')){
                $pm->updatePlugin($plugin);
            }
            if(!$plugin->getActive()) {
                $pm->activatePlugin($plugin);
            }
        }
    }

    /**
     * @param string $pluginName
     */
    private function removePlugin($pluginName)
    {
        $pluginDirs = $this->container->getParameter('shopware.plugin_directories');
        /** @var QueryBuilder $qb */
        $qb = $this->container->get('dbal_connection')->createQueryBuilder();
        $qb->select('namespace')
            ->from('s_core_plugins')
            ->where('name = :name')
            ->setParameter('name', $pluginName);
        $pluginNamespace = $qb->execute()->fetchColumn();

        if (!empty($pluginNamespace)) {
            if ($pluginNamespace !== 'ShopwarePlugins') {
                $pluginPath = $pluginDirs['Community'] . DIRECTORY_SEPARATOR . $pluginNamespace . DIRECTORY_SEPARATOR . $pluginName;
            } else {
                $pluginPath = $pluginDirs[$pluginNamespace] . DIRECTORY_SEPARATOR . $pluginName;
            }
            if (is_dir($pluginPath)) {
                $fs = new Filesystem();
                try {
                    $fs->remove($pluginPath);
                } catch (IOException $e) {
                    return false;
                }

                $qb = $this->container->get('dbal_connection')->createQueryBuilder();
                $qb->delete('s_core_plugins')
                    ->where('name = :name')
                    ->setParameter('name', $pluginName)
                    ->execute();

            }
        }
    }

    /**
     * Inserts a new page to an existing group
     * @param string $description
     * @param string $grouping - page group
     * @param int $active
     * @param int $position
     */
    public function insertPage($description, $grouping, $active, $position) {
            $sql = "REPLACE INTO s_cms_static SET
              description = ". $this->connection->quote($description).",
                 grouping = ". $this->connection->quote($grouping).",
                   active = ". $active .",
                 position = ". $position .",
                  changed = now()";

        $this->addSql($sql);
    }

    /**
     * Inserts a new page group
     * @param string $name
     * @param string $template - variable in template
     * @param int $active
     */
    public function insertPageGroup($name, $template, $active) {
        $sql ="REPLACE INTO s_cms_static_groups SET
 s_cms_static_groups.name = ". $this->connection->quote($name).",
                 template = ". $this->connection->quote($template).",
                   active = ". $active;
        $this->addSql($sql);
    }
}