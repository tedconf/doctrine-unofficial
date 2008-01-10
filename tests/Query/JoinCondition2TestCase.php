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
 * <http://www.phpdoctrine.com>.
 */

/**
 * Doctrine_Query_JoinCondition2_TestCase
 *
 * @package     Doctrine
 * @author      Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.com
 * @since       1.0
 * @version     $Revision$
 */
class Doctrine_Query_JoinCondition2_TestCase extends Doctrine_UnitTestCase 
{
    public function prepareTables()
    {
        $this->tables[] = 'Entity';
        $this->tables[] = 'User';
        $this->tables[] = 'GroupUser';
        $this->tables[] = 'Group';
        parent::prepareTables();
    }


    public function prepareData()
    {
        parent::prepareData();

        $groups = new Doctrine_Collection('Group');

        $groups[0]->name = 'PHP Users';

        $groups[1]->name = 'Developers';

        $groups->save();

        $zYne = Doctrine_Query::create()->from('User u')->where('u.id = ?', 4)->fetchOne();
        $zYne->Group[0] = $groups[0];
        $zYne->Group[1] = $groups[1];
        $zYne->save();
    }


    public function testJoinCondifitionsRawLeftJoins()
    {
        $q = new Doctrine_Query($this->connection);

        $q->select('u.id')->from('User u')->leftJoin('u.Group g WITH g.id = 12')->where('u.id = 4');

	$this->assertEqual($q->getQuery(), 'SELECT e.id AS e__id FROM entity e LEFT JOIN groupuser g ON e.id = g.user_id LEFT JOIN entity e2 ON e2.id = g.group_id AND e2.id = 12 WHERE e.id = 4 AND (e.type = 0 AND (e2.type = 1 OR e2.type IS NULL))');

        $rs = $q->execute();

        // Should only find zYne
        $this->assertEqual($rs->count(), 1);

        // Grab the number of runned queries
        $queryCount = $this->connection->count();

        // Only one Group fetched for zYne
        $this->assertEqual($rs[0]->Group->count(), 1);

        // Check if it executed any other query
        $bug = ($this->connection->count() - $queryCount);

        // Should return 0 (no more queries executed)
        $this->assertEqual($bug, 0);
    }


    public function testJoinCondifitionsArgumentsLeftJoins()
    {
        $q = new Doctrine_Query($this->connection);

        $q->select('u.id')->from('User u')->leftJoin('u.Group g WITH g.id = ?', 12)->where('u.id = ?', 4);

	$this->assertEqual($q->getQuery(), 'SELECT e.id AS e__id FROM entity e LEFT JOIN groupuser g ON e.id = g.user_id LEFT JOIN entity e2 ON e2.id = g.group_id AND e2.id = ? WHERE e.id = ? AND (e.type = 0 AND (e2.type = 1 OR e2.type IS NULL))');

        $rs = $q->execute();

        // Should only find zYne
        $this->assertEqual($rs->count(), 1);

        // Grab the number of runned queries
        $queryCount = $this->connection->count();

        // Only one Group fetched for zYne
        $this->assertEqual($rs[0]->Group->count(), 1);

        // Check if it executed any other query
        $bug = ($this->connection->count() - $queryCount);

        // Should return 0 (no more queries executed)
        $this->assertEqual($bug, 0);
    }


    public function testJoinCondifitionsRawInnerJoins()
    {
        $q = new Doctrine_Query($this->connection);

        $q->select('u.id')->from('User u')->innerJoin('u.Group g WITH g.id = 12')->where('u.id = 4');

	$this->assertEqual($q->getQuery(), 'SELECT e.id AS e__id FROM entity e INNER JOIN groupuser g ON e.id = g.user_id INNER JOIN entity e2 ON e2.id = g.group_id AND e2.id = 12 WHERE e.id = 4 AND (e.type = 0 AND (e2.type = 1 OR e2.type IS NULL))');

        $rs = $q->execute();

        // Should only find zYne
        $this->assertEqual($rs->count(), 1);

        // Grab the number of runned queries
        $queryCount = $this->connection->count();

        // Only one Group fetched for zYne
        $this->assertEqual($rs[0]->Group->count(), 1);

        // Check if it executed any other query
        $bug = ($this->connection->count() - $queryCount);

        // Should return 0 (no more queries executed)
        $this->assertEqual($bug, 0);
    }


    public function testJoinCondifitionsArgumentsInnerJoins()
    {
        $q = new Doctrine_Query($this->connection);

        $q->select('u.id')->from('User u')->innerJoin('u.Group g WITH g.id = ?', 12)->where('u.id = ?', 4);

	$this->assertEqual($q->getQuery(), 'SELECT e.id AS e__id FROM entity e INNER JOIN groupuser g ON e.id = g.user_id INNER JOIN entity e2 ON e2.id = g.group_id AND e2.id = ? WHERE e.id = ? AND (e.type = 0 AND (e2.type = 1 OR e2.type IS NULL))');

        $rs = $q->execute();

        // Should only find zYne
        $this->assertEqual($rs->count(), 1);

        // Grab the number of runned queries
        $queryCount = $this->connection->count();

        // Only one Group fetched for zYne
        $this->assertEqual($rs[0]->Group->count(), 1);

        // Check if it executed any other query
        $bug = ($this->connection->count() - $queryCount);

        // Should return 0 (no more queries executed)
        $this->assertEqual($bug, 0);
    }
}
