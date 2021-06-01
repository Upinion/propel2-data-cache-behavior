<?php

namespace TFC\Propel\Behavior\DataCache;

use TFC\Propel\Behavior\DataCache\DataCacheBehaviorQueryBuilderModifier;

/**
 * Propel Data Cache Behavior
 *
 * @copyright Copyright (c) 2013 Domino Co. Ltd.
 * @license MIT
 * @package propel.generator.behavior
 */

/**
 * Propel Data Cache Behavior
 *
 * @copyright Copyright (c) 2013 Domino Co. Ltd.
 * @license MIT
 * @package propel.generator.behavior
 */
class DataCacheBehavior extends \Propel\Generator\Model\Behavior
{
    protected $parameters = array(
        "backend"    => "apc",
        "lifetime"   => 3600,
        "auto_cache" => true,
        "auto_purge" => true,
    );
    protected $objectBuilderModifier;
    protected $queryBuilderModifier;

    public function __construct() {
        $this->setTableModificationOrder(100);
    }

    public function modifyTable() {
        $table = $this->getTable();
        if ($table->hasBehavior('i18n')) {
            $behavior = $table->getBehavior('i18n');
            $behavior->getI18nTable()->addBehavior([
                'name' => 'data_cache',
            ]);
            $behavior->getI18nTable()->getBehavior('data_cache')
                ->setParameters($this->getParameters());
        }
    }

    public function getQueryBuilderModifier()
    {
        if (is_null($this->queryBuilderModifier)) {
            $this->queryBuilderModifier = new DataCacheBehaviorQueryBuilderModifier($this);
        }
        return $this->queryBuilderModifier;
    }

    public function getObjectBuilderModifier()
    {
        if (is_null($this->objectBuilderModifier)) {
            $this->objectBuilderModifier = new DataCacheBehaviorObjectBuilderModifier($this);
        }
        return $this->objectBuilderModifier;
    }


}
