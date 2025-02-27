<?php
/**
 * Propel Data Cache Behavior
 *
 * @copyright Copyright (c) 2013 Domino Co. Ltd.
 * @license MIT
 * @package propel.generator.behavior
 */

/**
 * Propel Data Cache Behavior Query Builder Modifier
 *
 * @copyright Copyright (c) 2013 Domino Co. Ltd.
 * @license MIT
 * @package propel.generator.behavior
 */
namespace TFC\Propel\Behavior\DataCache;

use Propel\Generator\Util\PhpParser;

class DataCacheBehaviorQueryBuilderModifier
{
    protected $behavior;
    protected $builder;
    protected $table;
    protected $autoPurge;

    protected $tableClassName;

    public function __construct($behavior)
    {
        $this->behavior = $behavior;
        $this->table = $behavior->getTable();
        $this->autoPurge = $behavior->getParameter("auto_purge") === true
            || strtolower($behavior->getParameter("auto_purge")) === "true";
    }

    public function postUpdateQuery($builder)
    {
        $queryClassName = $builder->getStubQueryBuilder()->getClassname();

        return $this->autoPurge ?
            "{$queryClassName}::purgeCache();" : "";

    }

    public function postDeleteQuery($builder)
    {
        return $this->postUpdateQuery($builder);
    }

    public function queryAttributes($builder)
    {
        $lifetime = $this->behavior->getParameter("lifetime");
        $auto_cache = $this->behavior->getParameter("auto_cache");

        $script = "
protected \$cacheKey      = '';
protected \$cacheLocale   = '';
protected \$cacheEnable   = {$auto_cache};
protected \$cacheLifeTime = {$lifetime};
        ";

        return $script;
    }

    public function queryMethods($builder)
    {

        $builder->declareClasses('\Propel\Runtime\Propel');
        $this->tableClassName = $builder->getTableMapClassName();
        $this->builder = $builder;

        $script = "";
        $this->addPurgeCache($script);
        $this->addCacheFetch($script);
        $this->addCacheStore($script);
        $this->addCacheDelete($script);
        $this->addSetCache($script);
        $this->addSetCacheEnable($script);
        $this->addSetCacheDisable($script);
        $this->addIsCacheEnable($script);
        $this->addCacheGetFormatter($script);
        $this->addGetCacheKey($script);
        $this->addSetCacheKey($script);
        $this->addSetLocale($script);
        $this->addSetLifeTime($script);
        $this->addGetLifeTime($script);
        $this->addFind($script);
        $this->addFindOne($script);

        return $script;
    }


    protected function addPurgeCache(&$script)
    {
        $backend = $this->behavior->getParameter("backend");

        $script .= "
public static function purgeCache()
{

    \$driver = \\TFC\\Cache\\DoctrineCacheFactory::factory('{$backend}');
    \$driver->setNamespace({$this->tableClassName}::TABLE_NAME);

    return \$driver->deleteAll();

}
        ";
    }

    protected function addCacheFetch(&$script)
    {
        $backend = $this->behavior->getParameter("backend");
        $objectClassName = $this->builder->getStubObjectBuilder()->getClassname();

        $script .= "
public static function cacheFetch(\$key)
{

    \$driver = \\TFC\\Cache\\DoctrineCacheFactory::factory('{$backend}');
    \$driver->setNamespace({$this->tableClassName}::TABLE_NAME);

    \$result = \$driver->fetch(\$key);

    return \$result;
}
        ";
    }

    protected function addCacheStore(&$script)
    {
        $backend = $this->behavior->getParameter("backend");

        $script .= "
public static function cacheStore(\$key, \$data, \$lifetime)
{
    \$driver = \\TFC\\Cache\\DoctrineCacheFactory::factory('{$backend}');
    \$driver->setNamespace({$this->tableClassName}::TABLE_NAME);

    return \$driver->save(\$key,\$data,\$lifetime);
}
        ";
    }

    protected function addCacheDelete(&$script)
    {
        $backend = $this->behavior->getParameter("backend");

        $script .= "
public static function cacheDelete(\$key)
{
    \$driver = \\TFC\\Cache\\DoctrineCacheFactory::factory('{$backend}');
    \$driver->setNamespace({$this->tableClassName}::TABLE_NAME);

    return \$driver->delete(\$key);
}
        ";
    }

    protected function addSetCache(&$script)
    {
        $script .= "
public function setCache(\$value)
{
    \$this->cacheEnable = \$value;

    return \$this;
}
        ";
    }

    protected function addSetCacheEnable(&$script)
    {
        $script .= "
public function setCacheEnable()
{
    \$this->cacheEnable = true;

    return \$this;
}
        ";
    }

    protected function addSetCacheDisable(&$script)
    {
        $script .= "
public function setCacheDisable()
{
    \$this->cacheEnable = false;

    return \$this;
}
        ";
    }

    protected function addIsCacheEnable(&$script)
    {
        $script .= "
public function isCacheEnable()
{
    return (bool)\$this->cacheEnable;
}
        ";
    }

    protected function addGetCacheKey(&$script)
    {
        $script .= "
public function getCacheKey(\$overrideFormatter = null)
{
    if (\$this->cacheKey) {
        return \$this->cacheKey;
    }
    \$formatter   = \$overrideFormatter ? \$overrideFormatter : \$this->getFormatter();
    \$params      = array();
    \$sql_hash    = hash('md4', \$this->createSelectSql(\$params));
    \$params_hash = hash('md4', json_encode(\$params).get_class(\$formatter));
    \$locale      = \$this->cacheLocale ? '_' . \$this->cacheLocale : '';
    \$this->cacheKey = \$sql_hash . '_' . \$params_hash . \$locale;

    return \$this->cacheKey;
}
        ";
    }

    protected function addSetLocale(&$script)
    {
        $script .= "
public function setCacheLocale(\$locale)
{
    \$this->cacheLocale = \$locale;

    return \$this;
}
";
    }

    protected function addSetCacheKey(&$script)
    {
        $script .= "
public function setCacheKey(\$cacheKey)
{
    \$this->cacheKey = \$cacheKey;

    return \$this;
}
";
    }

    protected function addSetLifeTime(&$script)
    {
        $script .= "
public function setLifeTime(\$lifetime)
{
    \$this->cacheLifeTime = \$lifetime;

    return \$this;
}
        ";
    }

    protected function addGetLifeTime(&$script)
    {
        $script .= "
public function getLifeTime()
{
    return \$this->cacheLifeTime;
}
        ";
    }

    protected function addCacheGetFormatter(&$script)
    {
        // Problem with getFormatter overwriting the correct formatter   
        $script .= "
private function _cacheGetFormatter()
{
         if (\$this->select === null) {
            // leave early
            return \$this->getFormatter();
        }

        // select() needs the PropelSimpleArrayFormatter if no formatter given
        if (\$this->formatter === null) {
            return new \Propel\Runtime\Formatter\SimpleArrayFormatter();
        }
}
        ";
    }

    protected function addFind(&$script)
    {
        $queryClassName = $this->builder->getStubQueryBuilder()->getClassname();
        $className = $this->builder->getStubObjectBuilder()->getClassname();

        $script .= "
/**
 * Issue a SELECT query based on the current ModelCriteria
 * and format the list of results with the current formatter
 * By default, returns an array of model objects
 *
 * @param ConnectionInterface \$con an optional connection object
 *
 * @return \\Propel\\Runtime\\Collection\\ObjectCollection|array|mixed the list of results, formatted by the current formatter
 */
public function find(ConnectionInterface \$con = null)
{
    \$cacheFormatter = \$this->_cacheGetFormatter();
    if (\$this->isCacheEnable() && \$cache = {$queryClassName}::cacheFetch(\$this->getCacheKey(\$cacheFormatter))) {
        if (\$cache instanceof \\Propel\\Runtime\\Collection\\ObjectCollection) {
            \$criteria = \$this->isKeepQuery() ? clone \$this : \$this;
            \$formatter = \$criteria->getFormatter()->init(\$criteria);
            \$cache->setFormatter(\$formatter);
            return \$cache;
        } else if (is_array(\$cache)) {
            return \$cache; 
        }
    }

    if (null === \$con) {
        \$con = Propel::getServiceContainer()->getReadConnection(\$this->getDbName());
    }

    \$this->basePreSelect(\$con);
    \$criteria = \$this->isKeepQuery() ? clone \$this : \$this;
    \$dataFetcher = \$criteria->doSelect(\$con);

    \$data = \$criteria->getFormatter()->init(\$criteria)->format(\$dataFetcher);

    if (\$this->isCacheEnable() && \$data instanceof \\Propel\\Runtime\\Collection\\ObjectCollection) {
        {$queryClassName}::cacheStore(\$this->getCacheKey(), \$data, \$this->getLifeTime());
    } else if (\$this->isCacheEnable() && is_array(\$data)) {
        {$queryClassName}::cacheStore(\$this->getCacheKey(), \$data, \$this->getLifeTime());
    }

    return \$data;


}
        ";
    }

    protected function addFindOne(&$script)
    {
        $className = $this->builder->getStubObjectBuilder()->getClassname();
        $queryClassName = $this->builder->getStubQueryBuilder()->getClassname();

        $script .= "
/**
 * Issue a SELECT ... LIMIT 1 query based on the current ModelCriteria
 * and format the result with the current formatter
 * By default, returns a model object
 *
 * @param ConnectionInterface \$con an optional connection object
 *
 * @return mixed the result, formatted by the current formatter
 */
public function findOne(ConnectionInterface \$con  = null)
{
    \$cacheFormatter = \$this->_cacheGetFormatter();
    if (\$this->isCacheEnable() && \$cache = {$queryClassName}::cacheFetch(\$this->getCacheKey(\$cacheFormatter))) {
        if (\$cache instanceof {$className}) {
            return \$cache;
        }
    }

    if (null === \$con) {
        \$con = Propel::getServiceContainer()->getReadConnection(\$this->getDbName());
    }

    \$this->basePreSelect(\$con);
    \$criteria = \$this->isKeepQuery() ? clone \$this : \$this;
    \$criteria->limit(1);
    \$dataFetcher = \$criteria->doSelect(\$con);

    \$data = \$criteria->getFormatter()->init(\$criteria)->formatOne(\$dataFetcher);

    if (\$this->isCacheEnable() && \$data instanceof {$className}) {
        {$queryClassName}::cacheStore(\$this->getCacheKey(), \$data, \$this->getLifeTime());
    }

    return \$data;
}
        ";
    }

    public function queryFilter(&$script)
    {
        $parser = new PHPParser($script, true);
        $this->replaceFindPk($parser);
        $this->replaceDoDeleteAll($parser);

        $script = $parser->getCode();
    }

    protected function replaceDoDeleteAll(&$parser)
    {
        if ($this->autoPurge) {
            $queryClassName = $this->builder->getStubQueryBuilder()->getClassname();

            $search  = "\$con->commit();";
            $replace = "\$con->commit();\n            {$queryClassName}::purgeCache();";
            $script  = $parser->findMethod('doDeleteAll');
            $script  = str_replace($search, $replace, $script);

            $parser->replaceMethod("doDeleteAll", $script);
        }
    }

    protected function replaceFindPk(&$parser)
    {
        $search  = "return \$this->findPkSimple(\$key, \$con);";
        $replace = "return \$this->filterByPrimaryKey(\$key)->findOne(\$con);";
        $script  = $parser->findMethod('findPk');
        $script  = str_replace($search, $replace, $script);

        $parser->replaceMethod('findPk', $script);
    }
}
