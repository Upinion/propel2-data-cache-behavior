<?php
/**
 * Propel Data Cache Behavior
 *
 * @copyright Copyright (c) 2013 Domino Co. Ltd.
 * @license MIT
 * @package propel.generator.behavior
 */

/**
 * Propel Data Cache Behavior Object Build Modifier
 *
 * @copyright Copyright (c) 2013 Domino Co. Ltd.
 * @license MIT
 * @package propel.generator.behavior
 */
namespace TFC\Propel\Behavior\DataCache;

use Propel\Generator\Util\PhpParser;

class DataCacheBehaviorObjectBuilderModifier
{
    protected $behavior;
    protected $builder;
    protected $table;

    public function __construct($behavior)
    {
        $this->behavior = $behavior;
        $this->table    = $behavior->getTable();

    }

    public function postSave($builder)
    {
        $queryClassName = $builder->getStubQueryBuilder()->getClassname();

        return "{$queryClassName}::purgeCache();";
    }

    public function postDelete($builder)
    {
        return $this->postSave($builder);
    }

    public function objectFilter(&$script)
    {
        $parser = new PHPParser($script, true);
        $foreignClasses = [];

        $foreignTables = $this->table->getForeignKeys();
        foreach($foreignTables as $ff) {
            if ($ff->getForeignTable()->hasBehavior("data_cache")
                && !in_array($ff->getForeignTable()->getPhpName(), $foreignClasses)
            ) {
                if ($this->table->getPhpName() !== $ff->getForeignTable()->getPhpName()) {
                    $foreignClasses[] = $ff->getForeignTable()->getPhpName();
                    $this->addCacheOneForeignKey($parser, $ff->getForeignTable()->getPhpName());
                } else {
                    $foreignClasses[] = $ff->getForeignTable()->getPhpName();
                    $this->addCacheCircularForeignKey($parser, $ff->getForeignTable()->getPhpName());
                }
            }
        }
        $referrers = $this->table->getReferrers();
        foreach($referrers as $ff) {
            if ($this->table->getPhpName() !== $ff->getTable()->getPhpName()
                && $ff->getTable()->hasBehavior("data_cache")
                && !in_array($ff->getTable()->getPhpName(), $foreignClasses)
            ) {
                $foreignClasses[] = $ff->getTable()->getPhpName();
                $this->addCacheManyForeignKey($parser, $ff->getTable()->getPhpName());
            }
        }
        $script = $parser->getCode();
    }

    public function addCacheOneForeignKey(&$parser, $phpClass)
    {
        $methodName = "get".$phpClass;
        $script = $parser->findMethod($methodName);
        $script = str_replace(
            "ConnectionInterface \$con = null",
            "ConnectionInterface \$con = null, \$enableCache = false",
            $script
        );
        $script = str_replace(
            "Child".$phpClass."Query::create()",
            "(\$enableCache ? \nChild".$phpClass."Query::create()->setCacheEnable()\n:\nChild".$phpClass."Query::create())",
            $script
        );

        $parser->replaceMethod($methodName, $script);
    }

    public function addCacheManyForeignKey(&$parser, $phpClass)
    {
        $methodName = "get".$phpClass."s";
        $script = $parser->findMethod($methodName);
        $script = str_replace(
            "ConnectionInterface \$con = null",
            "ConnectionInterface \$con = null, \$enableCache = false",
            $script
        );
        $script = str_replace(
            "Child".$phpClass."Query::create(null, \$criteria)",
            "(\$enableCache ? \nChild".$phpClass."Query::create(null, \$criteria)->setCacheEnable()\n:\nChild".$phpClass."Query::create(null, \$criteria))",
            $script
        );

        $parser->replaceMethod($methodName, $script);
    }

    public function addCacheCircularForeignKey(&$parser, $phpClass)
    {
        $matches = [];
        $found = preg_match('/function (get'.$phpClass.'RelatedBy\w*Id)/', $parser->getCode(), $matches);
        if ($found) {
            $methodName = $matches[1];
            $script = $parser->findMethod($methodName);
            $script = str_replace(
                "ConnectionInterface \$con = null",
                "ConnectionInterface \$con = null, \$enableCache = false",
                $script
            );
            $script = str_replace(
                "Child".$phpClass."Query::create()",
                "(\$enableCache ? \nChild".$phpClass."Query::create()->setCacheEnable()\n:\nChild".$phpClass."Query::create())",
                $script
            );

            $parser->replaceMethod($methodName, $script);
        }
    
        $methodName = "get".$phpClass."sRelatedById";
        $script = $parser->findMethod($methodName);
        $script = str_replace(
            "ConnectionInterface \$con = null",
            "ConnectionInterface \$con = null, \$enableCache = false",
            $script
        );
        $script = str_replace(
            "Child".$phpClass."Query::create(null, \$criteria)",
            "(\$enableCache ? \nChild".$phpClass."Query::create(null, \$criteria)->setCacheEnable()\n:\nChild".$phpClass."Query::create(null, \$criteria))",
            $script
        );

        $parser->replaceMethod($methodName, $script);
    }
}
