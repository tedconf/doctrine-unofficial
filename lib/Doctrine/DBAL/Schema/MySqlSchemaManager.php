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

/**
 * Schema manager for the MySql RDBMS.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @author      Roman Borschel <roman@code-factory.org>
 * @version     $Revision$
 * @since       2.0
 */
class MySqlSchemaManager extends AbstractSchemaManager
{
    protected function _getPortableViewDefinition($view)
    {
        return $view['TABLE_NAME'];
    }

    protected function _getPortableTableDefinition($table)
    {
        return end($table);
    }

    protected function _getPortableUserDefinition($user)
    {
        return array(
            'user' => $user['User'],
            'password' => $user['Password'],
        );
    }

    protected function _getPortableTableIndexDefinition($tableIndex)
    {
        $tableIndex = array_change_key_case($tableIndex, CASE_LOWER);

        $result = array();
        if ($tableIndex['key_name'] != 'PRIMARY' && ($index = $tableIndex['key_name'])) {
            $result['name'] = $index;
            $result['column'] = $tableIndex['column_name'];
            $result['unique'] = $tableIndex['non_unique'] ? false : true;
        }

        return $result;
    }

    protected function _getPortableTableConstraintDefinition($tableConstraint)
    {
        $tableConstraint = array_change_key_case($tableConstraint, CASE_LOWER);

        if ( ! $tableConstraint['non_unique']) {
            $index = $tableConstraint['key_name'];
            if ( ! empty($index)) {
                return $index;
            }
        }
    }

    protected function _getPortableSequenceDefinition($sequence)
    {
        return end($sequence);
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        return $database['Database'];
    }
    
    /**
     * Gets a portable column definition.
     * 
     * The database type is mapped to a corresponding Doctrine mapping type.
     * 
     * @param $tableColumn
     * @return array
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $dbType = strtolower($tableColumn['Type']);
        $dbType = strtok($dbType, '(), ');
        if ($dbType == 'national') {
            $dbType = strtok('(), ');
        }
        if (isset($tableColumn['length'])) {
            $length = $tableColumn['length'];
            $decimal = '';
        } else {
            $length = strtok('(), ');
            $decimal = strtok('(), ') ? strtok('(), '):null;
        }
        $type = array();
        $unsigned = $fixed = null;

        if ( ! isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        $values = null;
        $scale = null;
        
        // Map db type to Doctrine mapping type
        switch ($dbType) {
            case 'tinyint':
                $type = 'boolean';
                $length = null;
                break;
            case 'smallint':
                $type = 'smallint';
                $length = null;
                break;
            case 'mediumint':
                $type = 'integer';
                $length = null;
                break;
            case 'int':
            case 'integer':
                $type = 'integer';
                $length = null;
                break;
            case 'bigint':
                $type = 'bigint';
                $length = null;
                break;
            case 'tinytext':
            case 'mediumtext':
            case 'longtext':
            case 'text':
                $type = 'text';
                $fixed = false;
                break;
            case 'varchar':
                $fixed = false;
            case 'string':
            case 'char':
                $type = 'string';
                if ($length == '1') {
                    $type = 'boolean';
                    if (preg_match('/^(is|has)/', $tableColumn['name'])) {
                        $type = array_reverse($type);
                    }
                } else if (strstr($dbType, 'text')) {
                    $type = 'text';
                    if ($decimal == 'binary') {
                        $type = 'blob';
                    }
                }
                if ($fixed !== false) {
                    $fixed = true;
                }
                break;
            case 'set':
                $fixed = false;
                $type = 'text';
                $type = 'integer'; //FIXME:???
                break;
            case 'date':
                $type = 'date';
                break;
            case 'datetime':
            case 'timestamp':
                $type = 'datetime';
                break;
            case 'time':
                $type = 'time';
                break;
            case 'float':
            case 'double':
            case 'real':
            case 'numeric':
                $type = 'decimal';
                break;
            case 'tinyblob':
            case 'mediumblob':
            case 'longblob':
            case 'blob':
            case 'binary':
            case 'varbinary':
                $type = 'blob';
                $length = null;
                break;
            case 'year':
                $type = 'integer';
                $type = 'date';
                $length = null;
                break;
            case 'geometry':
            case 'geometrycollection':
            case 'point':
            case 'multipoint':
            case 'linestring':
            case 'multilinestring':
            case 'polygon':
            case 'multipolygon':
                $type = 'blob';
                $length = null;
                break;
            default:
                $type = 'string';
                $length = null;
        }

        $length = ((int) $length == 0) ? null : (int) $length;
        $def =  array(
            'type' => $type,
            'length' => $length,
            'unsigned' => (bool) $unsigned,
            'fixed' => (bool) $fixed
        );
        
        if ($scale !== null) {
            $def['scale'] = $scale;
        }

        $values = ($values !== null) ? $values : array();

        $column = array(
            'name'          => $tableColumn['Field'],
            'values'        => $values,
            'primary'       => (bool) (strtolower($tableColumn['Key']) == 'pri'),
            'unique'        => (bool) (strtolower($tableColumn['Key']) == 'uni'),
            'default'       => $tableColumn['Default'],
            'notnull'       => (bool) ($tableColumn['Null'] != 'YES'),
            'autoincrement' => (bool) (strpos($tableColumn['Extra'], 'auto_increment') !== false),
        );

        return array_merge($column, $def);
    }

    public function _getPortableTableForeignKeyDefinition($tableForeignKey)
    {
        $tableForeignKey = array_change_key_case($tableForeignKey, CASE_LOWER);
        $foreignKey = array(
            'table'   => $tableForeignKey['referenced_table_name'],
            'local'   => $tableForeignKey['column_name'],
            'foreign' => $tableForeignKey['referenced_column_name']
        );
        return $foreignKey;
    }
    
    /**
     * {@inheritdoc}
     */
    public function createSequence($sequenceName, $start = 1, $allocationSize = 1)
    {
        $seqColumnName = 'mysql_sequence';

        /* No support for options yet. Might add 4th options parameter later
        $optionsStrings = array();
         
        if (isset($options['comment']) && ! empty($options['comment'])) {
            $optionsStrings['comment'] = 'COMMENT = ' . $this->_conn->quote($options['comment'], 'string');
        }

        if (isset($options['charset']) && ! empty($options['charset'])) {
            $optionsStrings['charset'] = 'DEFAULT CHARACTER SET ' . $options['charset'];

            if (isset($options['collate'])) {
                $optionsStrings['collate'] .= ' COLLATE ' . $options['collate'];
            }
        }
        
        $type = false;

        if (isset($options['type'])) {
            $type = $options['type'];
        } else {
            $type = $this->_conn->default_table_type;
        }
        if ($type) {
            $optionsStrings[] = 'ENGINE = ' . $type;
        }*/

        try {
            $query  = 'CREATE TABLE ' . $sequenceName
                    . ' (' . $seqcolName . ' INT NOT NULL AUTO_INCREMENT, PRIMARY KEY ('
                    . $seqcolName . '))';

            /*if (!empty($options_strings)) {
                $query .= ' '.implode(' ', $options_strings);
            }*/
            $query .= ' ENGINE = INNODB';

            $res = $this->_conn->exec($query);
        } catch(Doctrine\DBAL\ConnectionException $e) {
            throw \Doctrine\Common\DoctrineException::createSequenceFailed($query);
        }

        if ($start == 1) {
            return;
        }

        $query = 'INSERT INTO ' . $sequenceName
                . ' (' . $seqcolName . ') VALUES (' . ($start - 1) . ')';

        $res = $this->_conn->exec($query);

        // Handle error
        try {
            $res = $this->_conn->exec('DROP TABLE ' . $sequenceName);
        } catch (\Exception $e) {
            throw \Doctrine\Common\DoctrineException::couldNotDropSequenceTable($sequenceName);
        }
    
        return $res;
    }
}