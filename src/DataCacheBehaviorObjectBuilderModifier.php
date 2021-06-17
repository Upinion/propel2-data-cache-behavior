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
    protected $autoPurge;
    protected $hasNestedSet;

    public function __construct($behavior)
    {
        $this->behavior = $behavior;
        $this->table    = $behavior->getTable();
        $this->autoPurge = $behavior->getParameter("auto_purge") === true
            || strtolower($behavior->getParameter("auto_purge")) === "true";
        $this->hasNestedSet = $behavior->getTable()->hasBehavior("nested_set");

    }

    public function postSave($builder)
    {
        $queryClassName = $builder->getStubQueryBuilder()->getClassname();

        return $this->autoPurge ?
            "{$queryClassName}::purgeCache();" : "";
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

        if ($this->hasNestedSet) {
            $this->addNestedSet($parser, $this->table->getPhpName());
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
            "Child".$phpClass."Query::create()\n->setCache(\$enableCache)",
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
            "Child".$phpClass."Query::create(null, \$criteria)\n->setCache(\$enableCache)",
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
                "Child".$phpClass."Query::create()\n->setCache(\$enableCache)",
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
            "Child".$phpClass."Query::create(null, \$criteria)\n->setCache(\$enableCache)",
            $script
        );

        $parser->replaceMethod($methodName, $script);
    }

    public function addNestedSet(&$parser, $phpClass)
    {
        foreach (["getParent","getPrevSibling","getNextSibling"] as $methodName) {
            $script = $parser->findMethod($methodName);
            $script = str_replace(
                "ConnectionInterface \$con = null",
                "ConnectionInterface \$con = null, \$enableCache = false",
                $script
            );
            $script = str_replace(
                "Child".$phpClass."Query::create()",
                "Child".$phpClass."Query::create()\n->setCache(\$enableCache)",
                $script
            );

            $parser->replaceMethod($methodName, $script);
        }


        foreach (["getFirstChild","getLastChild","getChildren","getSiblings","getDescendants","getBranch","getAncestors"] as $methodName) {
            $methodName = "getLastChild";
            $script = $parser->findMethod($methodName);
            $script = str_replace(
                "ConnectionInterface \$con = null",
                "ConnectionInterface \$con = null, \$enableCache = false",
                $script
            );
            $script = str_replace(
                "Child".$phpClass."Query::create(null, \$criteria)",
                "Child".$phpClass."Query::create(null, \$criteria)\n->setCache(\$enableCache)",
                $script
            );

            $parser->replaceMethod($methodName, $script);
        }
    }
}
