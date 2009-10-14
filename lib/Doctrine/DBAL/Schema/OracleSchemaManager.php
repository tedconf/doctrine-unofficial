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
 * <http://www.phpdoctrine.org>.
 */

namespace Doctrine\DBAL\Schema;

/**
 * xxx
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @version     $Revision$
 * @since       2.0
 */
class OracleSchemaManager extends AbstractSchemaManager
{
    protected function _getPortableViewDefinition($view)
    {
        return array(
            'name' => $view['view_name']
        );
    }

    protected function _getPortableUserDefinition($user)
    {
        return array(
            'user' => $user['username'],
            'password' => $user['password']
        );
    }

    protected function _getPortableTableDefinition($table)
    {
        return $table['table_name'];
    }

    protected function _getPortableTableIndexDefinition($tableIndex)
    {
        return array(
            'name' => $tableIndex['index_name'],
            'unique' => (isset($tableIndex['uniqueness']) && $tableIndex['uniqueness'] == 'UNIQUE') ? true : false
        );
    }

    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $dbType = strtolower($tableColumn['data_type']);
        $type = array();
        $length = $unsigned = $fixed = null;
        if ( ! empty($tableColumn['data_length'])) {
            $length = $tableColumn['data_length'];
        }

        if ( ! isset($tableColumn['column_name'])) {
            $tableColumn['column_name'] = '';
        }

        if (stripos($tableColumn['data_default'], 'NULL') !== null) {
            $tableColumn['data_default'] = null;
        }

        switch ($dbType) {
            case 'integer':
            case 'number':
                $type = 'integer';
                $length = null;
                break;
            case 'pls_integer':
            case 'binary_integer':
                $type = 'boolean';
                $length = null;
                break;
            case 'varchar':
            case 'varchar2':
            case 'nvarchar2':
                $fixed = false;
            case 'char':
            case 'nchar':
                if ($length == '1' && preg_match('/^(is|has)/', $tableColumn['column_name'])) {
                    $type = 'boolean';
                } else {
                    $type = 'string';
                }
                if ($fixed !== false) {
                    $fixed = true;
                }
                break;
            case 'date':
            case 'timestamp':
                $type = 'datetime';
                $length = null;
                break;
            case 'float':
                $type = 'decimal';
                $length = null;
                break;
            case 'long':
                $type = 'string';
            case 'clob':
            case 'nclob':
                $type = 'text';
                break;
            case 'blob':
            case 'raw':
            case 'long raw':
            case 'bfile':
                $type = 'blob';
                $length = null;
            break;
            case 'rowid':
            case 'urowid':
            default:
                $type = 'string';
                $length = null;
        }

        $decl = array(
            'type'     => $type,
            'length'   => $length,
            'unsigned' => $unsigned,
            'fixed'    => $fixed
        );

        return array(
           'name'       => $tableColumn['column_name'],
           'notnull'    => (bool) ($tableColumn['nullable'] === 'N'),
           'type'       => $decl['type'],
           'fixed'      => (bool) $decl['fixed'],
           'unsigned'   => (bool) $decl['unsigned'],
           'default'    => $tableColumn['data_default'],
           'length'     => $tableColumn['data_length'],
           'precision'  => $tableColumn['data_precision'],
           'scale'      => $tableColumn['data_scale'],
        );
    }

    protected function _getPortableTableConstraintDefinition($tableConstraint)
    {
        return $tableConstraint['constraint_name'];
    }

    protected function _getPortableFunctionDefinition($function)
    {
        return $function['name'];
    }

    protected function _getPortableDatabaseDefinition($database)
    {
        return $database['username'];
    }

    public function createDatabase($database = null)
    {
        if (is_null($database)) {
            $database = $this->_conn->getDatabase();
        }

        $params = $this->_conn->getParams();
        $username   = $database;
        $password   = $params['password'];

        $query  = 'CREATE USER ' . $username . ' IDENTIFIED BY ' . $password;
        $result = $this->_conn->executeUpdate($query);

        $query = 'GRANT CREATE SESSION, CREATE TABLE, UNLIMITED TABLESPACE, CREATE SEQUENCE, CREATE TRIGGER TO ' . $username;
        $result = $this->_conn->executeUpdate($query);

        return true;
    }

    public function dropAutoincrement($table)
    {
        $sql = $this->_platform->getDropAutoincrementSql($table);
        foreach ($sql as $query) {
            $this->_conn->executeUpdate($query);
        }

        return true;
    }

    /**
     * getAdvancedForeignKeyOptions
     * Return the FOREIGN KEY query section dealing with non-standard options
     * as MATCH, INITIALLY DEFERRED, ON UPDATE, ...
     *
     * @param array $definition         foreign key definition
     * @return string
     * @access protected
     */
    public function getAdvancedForeignKeyOptions(array $definition)
    {
        $query = '';
        if (isset($definition['onDelete'])) {
            $query .= ' ON DELETE ' . $definition['onDelete'];
        }
        if (isset($definition['deferrable'])) {
            $query .= ' DEFERRABLE';
        } else {
            $query .= ' NOT DEFERRABLE';
        }
        if (isset($definition['feferred'])) {
            $query .= ' INITIALLY DEFERRED';
        } else {
            $query .= ' INITIALLY IMMEDIATE';
        }
        return $query;
    }

    public function dropTable($name)
    {
        $this->dropAutoincrement($name);

        return parent::dropTable($name);
    }
}