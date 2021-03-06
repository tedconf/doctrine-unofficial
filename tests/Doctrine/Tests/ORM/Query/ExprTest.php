<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\Tests\ORM\Query;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query;

require_once __DIR__ . '/../../TestInit.php';

/**
 * Test case for the DQL Expr class used for generating DQL snippets through 
 * a programmatic interface
 *
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        http://www.phpdoctrine.org
 * @since       2.0
 * @version     $Revision$
 */
class ExprTest extends \Doctrine\Tests\OrmTestCase
{
    private $_em;

    protected function setUp()
    {
        $this->_em = $this->_getTestEntityManager();
        $this->_expr = new Expr;
    }

    public function testAvgExpr()
    {
        $this->assertEquals('AVG(u.id)', (string) $this->_expr->avg('u.id'));
    }

    public function testMaxExpr()
    {
        $this->assertEquals('MAX(u.id)', (string) $this->_expr->max('u.id'));
    }

    public function testMinExpr()
    {
        $this->assertEquals('MIN(u.id)', (string) $this->_expr->min('u.id'));
    }

    public function testCountExpr()
    {
        $this->assertEquals('MAX(u.id)', (string) $this->_expr->max('u.id'));
    }

    public function testCountDistinctExpr()
    {
        $this->assertEquals('COUNT(DISTINCT u.id)', (string) $this->_expr->countDistinct('u.id'));
    }

    public function testExistsExpr()
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('u')->from('User', 'u')->where('u.name = ?1');
        
        $this->assertEquals('EXISTS(SELECT u FROM User u WHERE u.name = ?1)', (string) $this->_expr->exists($qb));
    }

    public function testAllExpr()
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('u')->from('User', 'u')->where('u.name = ?1');
    
        $this->assertEquals('ALL(SELECT u FROM User u WHERE u.name = ?1)', (string) $this->_expr->all($qb));
    }

    public function testSomeExpr()
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('u')->from('User', 'u')->where('u.name = ?1');
    
        $this->assertEquals('SOME(SELECT u FROM User u WHERE u.name = ?1)', (string) $this->_expr->some($qb));
    }

    public function testAnyExpr()
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('u')->from('User', 'u')->where('u.name = ?1');
    
        $this->assertEquals('ANY(SELECT u FROM User u WHERE u.name = ?1)', (string) $this->_expr->any($qb));
    }

    public function testNotExpr()
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select('u')->from('User', 'u')->where('u.name = ?1');
    
        $this->assertEquals('NOT(SELECT u FROM User u WHERE u.name = ?1)', (string) $this->_expr->not($qb));
    }

    public function testAndExpr()
    {
        $this->assertEquals('(1 = 1) AND (2 = 2)', (string) $this->_expr->andx((string) $this->_expr->eq(1, 1), (string) $this->_expr->eq(2, 2)));
    }
    
    public function testIntelligentParenthesisPreventionAndExpr()
    {
        $this->assertEquals(
            '(1 = 1) AND (2 = 2)', 
            (string) $this->_expr->andx($this->_expr->orx($this->_expr->andx($this->_expr->eq(1, 1))), (string) $this->_expr->eq(2, 2))
        );
    }

    public function testOrExpr()
    {
        $this->assertEquals('(1 = 1) OR (2 = 2)', (string) $this->_expr->orx((string) $this->_expr->eq(1, 1), (string) $this->_expr->eq(2, 2)));
    }

    public function testAbsExpr()
    {
        $this->assertEquals('ABS(1)', (string) $this->_expr->abs(1));
    }

    public function testProdExpr()
    {
        $this->assertEquals('1 * 2', (string) $this->_expr->prod(1, 2));
    }

    public function testDiffExpr()
    {
        $this->assertEquals('1 - 2', (string) $this->_expr->diff(1, 2));
    }

    public function testSumExpr()
    {
        $this->assertEquals('1 + 2', (string) $this->_expr->sum(1, 2));
    }

    public function testQuotientExpr()
    {
        $this->assertEquals('10 / 2', (string) $this->_expr->quot(10, 2));
    }
    
    public function testScopeInArithmeticExpr()
    {
        $this->assertEquals('(100 - 20) / 2', (string) $this->_expr->quot($this->_expr->diff(100, 20), 2));
        $this->assertEquals('100 - (20 / 2)', (string) $this->_expr->diff(100, $this->_expr->quot(20, 2)));
    }

    public function testSquareRootExpr()
    {
        $this->assertEquals('SQRT(1)', (string) $this->_expr->sqrt(1));
    }

    public function testEqualExpr()
    {
        $this->assertEquals('1 = 1', (string) $this->_expr->eq(1, 1));
    }

    public function testLikeExpr()
    {
        $this->assertEquals('a.description LIKE :description', (string) $this->_expr->like('a.description', ':description'));
    }

    public function testConcatExpr()
    {
        $this->assertEquals('CONCAT(u.first_name, u.last_name)', (string) $this->_expr->concat('u.first_name', 'u.last_name'));
    }

    public function testSubstringExpr()
    {
        $this->assertEquals('SUBSTRING(a.title, 0, 25)', (string) $this->_expr->substring('a.title', 0, 25));
    }

    public function testLowerExpr()
    {
        $this->assertEquals('LOWER(u.first_name)', (string) $this->_expr->lower('u.first_name'));
    }

    public function testUpperExpr()
    {
        $this->assertEquals('UPPER(u.first_name)', (string) $this->_expr->upper('u.first_name'));
    }

    public function testLengthExpr()
    {
        $this->assertEquals('LENGTH(u.first_name)', (string) $this->_expr->length('u.first_name'));
    }

    public function testGreaterThanExpr()
    {
        $this->assertEquals('5 > 2', (string) $this->_expr->gt(5, 2));
    }

    public function testLessThanExpr()
    {
        $this->assertEquals('2 < 5', (string) $this->_expr->lt(2, 5));
    }

    public function testStringLiteralExpr()
    {
        $this->assertEquals("'word'", (string) $this->_expr->literal('word'));
    }

    public function testNumericLiteralExpr()
    {
        $this->assertEquals(5, (string) $this->_expr->literal(5));
    }

    public function testGreaterThanOrEqualToExpr()
    {
        $this->assertEquals('5 >= 2', (string) $this->_expr->gte(5, 2));
    }

    public function testLessThanOrEqualTo()
    {
        $this->assertEquals('2 <= 5', (string) $this->_expr->lte(2, 5));
    }

    public function testBetweenExpr()
    {
        $this->assertEquals('u.id BETWEEN 3 AND 6', (string) $this->_expr->between('u.id', 3, 6));
    }

    public function testTrimExpr()
    {
        $this->assertEquals('TRIM(u.id)', (string) $this->_expr->trim('u.id'));
    }

    public function testInExpr()
    {
        $this->assertEquals('u.id IN(1, 2, 3)', (string) $this->_expr->in('u.id', array(1, 2, 3)));
    }

    public function testAndxOrxExpr()
    {
        $andExpr = $this->_expr->andx();
        $andExpr->add($this->_expr->eq(1, 1));
        $andExpr->add($this->_expr->lt(1, 5));

        $orExpr = $this->_expr->orx();
        $orExpr->add($andExpr);
        $orExpr->add($this->_expr->eq(1, 1));

        $this->assertEquals('((1 = 1) AND (1 < 5)) OR (1 = 1)', (string) $orExpr);
    }

    public function testOrxExpr()
    {
        $orExpr = $this->_expr->orx();
        $orExpr->add($this->_expr->eq(1, 1));
        $orExpr->add($this->_expr->lt(1, 5));

        $this->assertEquals('(1 = 1) OR (1 < 5)', (string) $orExpr);
    }
    
    public function testOrderByCountExpr()
    {
        $orderExpr = $this->_expr->desc('u.username');

        $this->assertEquals($orderExpr->count(), 1);
        $this->assertEquals('u.username DESC', (string) $orderExpr);
    }

    public function testOrderByOrder()
    {
        $orderExpr = $this->_expr->desc('u.username');
        $this->assertEquals('u.username DESC', (string) $orderExpr);
    }

    public function testOrderByAsc()
    {
        $orderExpr = $this->_expr->asc('u.username');
        $this->assertEquals('u.username ASC', (string) $orderExpr);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddThrowsException()
    {
        $orExpr = $this->_expr->orx();
        $orExpr->add($this->_expr->quot(5, 2));
    }
}