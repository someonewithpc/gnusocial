<?php

// {{{ License
// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.
// }}}

/**
 * Doctrine entity manager static wrapper
 *
 * @package GNUsocial
 * @category DB
 *
 * @author    Hugo Sales <hugo@fc.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Core\DB;

use App\Util\Formatting;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ExpressionBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

abstract class DB
{
    private static ?EntityManagerInterface $em;
    public static function setManager($m): void
    {
        self::$em = $m;
    }

    private static array $find_by_ops = ['or', 'and', 'eq', 'neq', 'lt', 'lte',
        'gt', 'gte', 'is_null', 'in', 'not_in',
        'contains', 'member_of', 'starts_with', 'ends_with', ];

    private static function buildExpression(ExpressionBuilder $eb, array $criteria)
    {
        $expressions = [];
        foreach ($criteria as $op => $exp) {
            if ($op == 'or' || $op == 'and') {
                $method = "{$op}X";
                return $eb->{$method}(...self::buildExpression($eb, $exp));
            } elseif ($op == 'is_null') {
                $expressions[] = $eb->isNull($exp);
            } else {
                if (in_array($op, self::$find_by_ops)) {
                    $method        = Formatting::snakeCaseToCamelCase($op);
                    $expressions[] = $eb->{$method}(...$exp);
                } else {
                    $expressions[] = $eb->eq($op, $exp);
                }
            }
        }

        return $expressions;
    }

    public static function findBy(string $table, array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $criteria = array_change_key_case($criteria);
        $ops      = array_intersect(array_keys($criteria), self::$find_by_ops);
        $repo     = self::getRepository($table);
        if (empty($ops)) {
            return $repo->findBy($criteria, $orderBy, $limit, $offset);
        } else {
            $criteria = new Criteria(self::buildExpression(Criteria::expr(), $criteria), $orderBy, $offset, $limit);
            return $repo->matching($criteria)->toArray(); // Always work with array or it becomes really complicated
        }
    }

    public static function findOneBy(string $table, array $criteria, ?array $orderBy = null, ?int $offset = null)
    {
        $res = self::findBy($table, $criteria, $orderBy, 1, $offset);
        if (count($res) == 1) {
            return $res[0];
        } else {
            throw new Exception("No value in table {$table} matches the requested criteria");
        }
    }

    public static function __callStatic(string $name, array $args)
    {
        foreach (['find', 'getReference', 'getPartialReference', 'getRepository'] as $m) {
            if ($name == $m) {
                $args[0] = '\App\Entity\\' . ucfirst(Formatting::snakeCaseToCamelCase($args[0]));
            }
        }

        return self::$em->{$name}(...$args);
    }
}
