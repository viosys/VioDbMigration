<?php
/**
 * Created by PhpStorm.
 * User: Sebastian König
 * Date: 03.03.2017
 * Time: 13:31
 */

namespace  Shopware\Components\Migrations;

use Shopware\Components\DependencyInjection\Container;

abstract class VioAbstractMigration extends AbstractMigration{

    /** @var  Container $container */
    protected $container;

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
         created = now(),
         updated = now()";

        $this->addSql($sql);
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;
        return $this;
    }

}