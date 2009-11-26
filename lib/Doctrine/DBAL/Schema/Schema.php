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

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Visitor\CreateSchemaSqlCollector;

/**
 * Object representation of a database schema
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision$
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 */
class Schema extends AbstractAsset
{
    /**
     * @var array
     */
    protected $_tables = array();

    /**
     * @var array
     */
    protected $_sequences = array();

    /**
     * @param array $tables
     * @param array $sequences
     */
    public function __construct(array $tables=array(), array $sequences=array())
    {
        foreach ($tables AS $table) {
            $this->_addTable($table);
        }
        foreach ($sequences AS $sequence) {
            $this->_addSequence($sequence);
        }
    }

    /**
     * @param Table $table
     */
    protected function _addTable(Table $table)
    {
        $tableName = $table->getName();
        if(isset($this->_tables[$tableName])) {
            throw SchemaException::tableAlreadyExists($tableName);
        }

        $this->_tables[$tableName] = $table;
    }

    /**
     * @param Sequence $sequence
     */
    protected function _addSequence(Sequence $sequence)
    {
        $seqName = $sequence->getName();
        if (isset($this->_sequences[$seqName])) {
            throw SchemaException::sequenceAlreadyExists($seqName);
        }
        $this->_sequences[$seqName] = $sequence;
    }

    /**
     * Get all tables of this schema.
     * 
     * @return array
     */
    public function getTables()
    {
        return $this->_tables;
    }

    /**
     * @param string $tableName
     * @return Table
     */
    public function getTable($tableName)
    {
        if (!isset($this->_tables[$tableName])) {
            throw SchemaException::tableDoesNotExist($tableName);
        }

        return $this->_tables[$tableName];
    }

    /**
     * Does this schema have a table with the given name?
     * 
     * @param  string $tableName
     * @return Schema
     */
    public function hasTable($tableName)
    {
        return isset($this->_tables[$tableName]);
    }

    /**
     * @param  string $sequenceName
     * @return bool
     */
    public function hasSequence($sequenceName)
    {
        return isset($this->_sequences[$sequenceName]);
    }

    /**
     * @throws SchemaException
     * @param  string $sequenceName
     * @return Doctrine\DBAL\Schema\Sequence
     */
    public function getSequence($sequenceName)
    {
        if(!$this->hasSequence($sequenceName)) {
            throw SchemaException::sequenceDoesNotExist($sequenceName);
        }
        return $this->_sequences[$sequenceName];
    }

    /**
     * @return Doctrine\DBAL\Schema\Sequence[]
     */
    public function getSequences()
    {
        return $this->_sequences;
    }

    /**
     * Create a new table
     * 
     * @param  string $tableName
     * @return Table
     */
    public function createTable($tableName)
    {
        $table = new Table($tableName);
        $this->_addTable($table);
        return $table;
    }

    /**
     * Rename a table
     *
     * @param string $oldTableName
     * @param string $newTableName
     * @return Schema
     */
    public function renameTable($oldTableName, $newTableName)
    {
        $table = $this->getTable($oldTableName);
        $table->_setName($newTableName);

        $this->dropTable($oldTableName);
        $this->_addTable($table);
        return $this;
    }

    /**
     * Drop a table from the schema.
     *
     * @param string $tableName
     * @return Schema
     */
    public function dropTable($tableName)
    {
        $table = $this->getTable($tableName);
        unset($this->_tables[$tableName]);
        return $this;
    }

    /**
     * Create a new sequence
     * 
     * @param  string $sequenceName
     * @param  int $allocationSize
     * @param  int $initialValue
     * @return Schema
     */
    public function createSequence($sequenceName, $allocationSize=1, $initialValue=1)
    {
        $seq = new Sequence($sequenceName, $allocationSize, $initialValue);
        $this->_addSequence($seq);
        return $this;
    }

    /**
     * @param string $sequenceName
     * @return Schema
     */
    public function dropSequence($sequenceName)
    {
        unset($this->_sequences[$sequenceName]);
        return $this;
    }

    /**
     * @param AbstractPlatform $platform
     */
    public function toSql(\Doctrine\DBAL\Platforms\AbstractPlatform $platform)
    {
        $sqlCollector = new CreateSchemaSqlCollector($platform);
        $this->visit($sqlCollector);

        return $sqlCollector->getQueries();
    }

    /**
     * @param Visitor $visitor
     */
    public function visit(Visitor $visitor)
    {
        $visitor->acceptSchema($this);
        
        foreach ($this->_tables AS $table) {
            $table->visit($visitor);
        }
        foreach ($this->_sequences AS $sequence) {
            $sequence->visit($visitor);
        }
    }
}
