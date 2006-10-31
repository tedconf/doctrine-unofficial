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
 * Doctrine_Connection
 *
 * @package     Doctrine ORM
 * @url         www.phpdoctrine.com
 * @license     LGPL
 */
abstract class Doctrine_Connection extends Doctrine_Configurable implements Countable, IteratorAggregate {
    /**
     * @var $dbh                                the database handler
     */
    private $dbh;
    /**
     * @var Doctrine_Transaction $transaction   the transaction object
     */
    private $transaction;
    /**
     * @var Doctrine_UnitOfWork $unitOfWork     the unit of work object
     */
    private $unitOfWork;
    /**
     * @var array $tables                       an array containing all the initialized Doctrine_Table objects
     *                                          keys representing Doctrine_Table component names and values as Doctrine_Table objects
     */
    protected $tables           = array();
    /**
     * @var string $driverName                  the name of this connection driver
     */
    protected $driverName;
    /**
     * @var array $supported                    an array containing all features this driver supports, 
     *                                          keys representing feature names and values as 
     *                                          one of the following (true, false, 'emulated')
     */
    protected $supported        = array();
    /**
     * @var Doctrine_DataDict $dataDict
     */
    private $dataDict;
    
    
    private static $availibleDrivers    = array(
                                        'Mysql',
                                        'Pgsql',
                                        'Oracle',
                                        'Informix',
                                        'Mssql',
                                        'Sqlite',
                                        'Firebird'
                                        );

    /**
     * the constructor
     *
     * @param Doctrine_Manager $manager     the manager object
     * @param PDO $pdo                      the database handler
     */
    public function __construct(Doctrine_Manager $manager, PDO $pdo) {
        $this->dbh   = $pdo;

        $this->transaction  = new Doctrine_Connection_Transaction($this);
        $this->unitOfWork   = new Doctrine_Connection_UnitOfWork($this);

        $this->setParent($manager);

        $this->dbh->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);   

        $this->getAttribute(Doctrine::ATTR_LISTENER)->onOpen($this);
    }
    /**
     * getName
     *
     * @return string           returns the name of this driver
     */
    public function getName() {
        return $this->driverName;
    }

    /**
     * quoteIdentifier
     *
     * @param string $identifier        identifier to be quoted
     * @return string                   modified identifier
     */
    public function quoteIdentifier($identifier) {
        return $identifier;
    }
    /**
     * getUnitOfWork
     *
     * returns the unit of work object
     *
     * @return Doctrine_UnitOfWork
     */
    public function getUnitOfWork() {
        return $this->unitOfWork;
    }
    /**
     * getTransaction
     *
     * returns the current transaction object
     *
     * @return Doctrine_Transaction
     */
    public function getTransaction() {
        return $this->transaction;
    }
    /**
     * returns the manager that created this connection
     *
     * @return Doctrine_Manager
     */
    public function getManager() {
        return $this->getParent();
    }
    /**
     * returns the database handler of which this connection uses
     *
     * @return PDO              the database handler
     */
    public function getDBH() {
        return $this->dbh;
    }
    /**
     * converts given driver name
     *
     * @param
     */
    public function driverName($name) {
    }
    /**
     * returns a datadict object
     *
     * @return Doctrine_DataDict
     */
    public function getDataDict() {
        if(isset($this->dataDict))
            return $this->dataDict;

        $class = 'Doctrine_DataDict_' . $this->getName();
        $this->dataDict = new $class($this->dbh);

        return $this->dataDict;
    }
    /**
     * returns the next value in the given sequence
     *
     * @param string $sequence
     * @throws PDOException     if something went wrong at database level
     * @return integer
     */
    public function nextId($sequence) {
        throw new Doctrine_Connection_Exception('NextId() for sequences not supported by this driver.');
    }
    /**
     * Set the charset on the current connection
     *
     * @param string    charset
     * @param resource  connection handle
     *
     * @throws Doctrine_Connection_Exception            if the feature is not supported by the driver
     * @return true on success, MDB2 Error Object on failure
     */
    public function setCharset($charset) {
        throw new Doctrine_Connection_Exception('Altering charset not supported by this driver.');
    }
    /**
     * setTransactionIsolation
     *
     * Set the transacton isolation level.
     * (implemented by the connection drivers)
     *
     * example:
     *
     * <code>
     * $conn->setTransactionIsolation('READ UNCOMMITTED');
     * </code>
     *
     * @param   string  standard isolation level
     *                  READ UNCOMMITTED (allows dirty reads)
     *                  READ COMMITTED (prevents dirty reads)
     *                  REPEATABLE READ (prevents nonrepeatable reads)
     *                  SERIALIZABLE (prevents phantom reads)
     *
     * @throws Doctrine_Connection_Exception            if the feature is not supported by the driver
     * @throws PDOException                             if something fails at the PDO level
     * @return void
     */
    public function setTransactionIsolation($isolation) {
        throw new Doctrine_Connection_Exception('Transaction isolation levels not supported by this driver.');
    }

    /**
     * getTransactionIsolation
     * 
     * @throws Doctrine_Connection_Exception            if the feature is not supported by the driver
     * @throws PDOException                             if something fails at the PDO level
     * @return string               returns the current session transaction isolation level
     */
    public function getTransactionIsolation() {
        throw new Doctrine_Connection_Exception('Fetching transaction isolation level not supported by this driver.');
    }
    /**
     * query
     * queries the database using Doctrine Query Language
     *
     * <code>
     * $users = $conn->query('SELECT u.* FROM User u');
     *
     * $users = $conn->query('SELECT u.* FROM User u WHERE u.name LIKE ?', array('someone'));
     * </code>
     *
     * @param string $query             DQL query
     * @param array $params             query parameters
     * @see Doctrine_Query
     * @return Doctrine_Collection      Collection of Doctrine_Record objects
     */
    public function query($query, array $params = array()) {
        $parser = new Doctrine_Query($this);

        return $parser->query($query, $params);
    }
    /**
     * queries the database with limit and offset
     * added to the query and returns a PDOStatement object
     *
     * @param string $query
     * @param integer $limit
     * @param integer $offset
     * @return PDOStatement
     */
    public function select($query,$limit = 0,$offset = 0) {
        if($limit > 0 || $offset > 0)
            $query = $this->modifyLimitQuery($query, $limit, $offset);

        return $this->dbh->query($query);
    }
    /**
     * @param string $query     sql query
     * @param array $params     query parameters
     *
     * @return PDOStatement
     */
    public function execute($query, array $params = array()) {
        if( ! empty($params)) {
            $stmt = $this->dbh->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } else {
            return $this->dbh->query($query);
        }
    }
    /**
     * hasTable
     * whether or not this connection has table $name initialized
     *
     * @param mixed $name
     * @return boolean
     */
    public function hasTable($name) {
        return isset($this->tables[$name]);
    }
    /**
     * returns a table object for given component name
     *
     * @param string $name              component name
     * @return object Doctrine_Table
     */
    public function getTable($name) {
        if(isset($this->tables[$name]))
            return $this->tables[$name];

        $class = $name."Table";

        if(class_exists($class) && in_array("Doctrine_Table", class_parents($class))) {
            return new $class($name, $this);
        } else {

            return new Doctrine_Table($name, $this);
        }
    }
    /**
     * returns an array of all initialized tables
     *
     * @return array
     */
    public function getTables() {
        return $this->tables;
    }
    /**
     * returns an iterator that iterators through all 
     * initialized table objects
     *
     * @return ArrayIterator
     */
    public function getIterator() {
        return new ArrayIterator($this->tables);
    }
    /**
     * returns the count of initialized table objects
     *
     * @return integer
     */
    public function count() {
        return count($this->tables);
    }
    /**
     * addTable
     * adds a Doctrine_Table object into connection registry
     *
     * @param $objTable             a Doctrine_Table object to be added into registry
     * @return boolean
     */
    public function addTable(Doctrine_Table $objTable) {
        $name = $objTable->getComponentName();

        if(isset($this->tables[$name]))
            return false;

        $this->tables[$name] = $objTable;
        return true;
    }
    /**
     * create
     * creates a record
     *
     * create                       creates a record
     * @param string $name          component name
     * @return Doctrine_Record      Doctrine_Record object
     */
    public function create($name) {
        return $this->getTable($name)->create();
    }
    /**
     * flush                        
     * saves all the records from all tables
     * this operation is isolated using a transaction
     *
     * @return void
     */
    public function flush() {
        $this->beginTransaction();
        $this->saveAll();
        $this->commit();
    }
    /**
     * saveAll                      
     * persists all the records from all tables
     *
     * @return void
     */
    private function saveAll() {
        $tree = $this->unitOfWork->buildFlushTree($this->tables);

        foreach($tree as $name) {
            $table = $this->tables[$name];

            foreach($table->getRepository() as $record) {
                $this->save($record);
            }
        }
        foreach($tree as $name) {
            $table = $this->tables[$name];
            foreach($table->getRepository() as $record) {
                $this->unitOfWork->saveAssociations($record);
            }
        }
    }
    /**
     * clear
     * clears all repositories
     *
     * @return void
     */
    public function clear() {
        foreach($this->tables as $k => $table) {
            $table->getRepository()->evictAll();
            $table->clear();
        }
    }
    /**
     * evictTables
     * evicts all tables
     *
     * @return void
     */
    public function evictTables() {
        $this->tables = array();
    }
    /**
     * close
     * closes the connection
     *
     * @return void
     */
    public function close() {
        $this->getAttribute(Doctrine::ATTR_LISTENER)->onPreClose($this);

        $this->clear();
        $this->state = Doctrine_Connection::STATE_CLOSED;
        
        $this->getAttribute(Doctrine::ATTR_LISTENER)->onClose($this);
    }
    /**
     * get the current transaction nesting level
     *
     * @return integer
     */
    public function getTransactionLevel() {
        return $this->transaction->getTransactionLevel();
    }
    /**
     * beginTransaction
     * starts a new transaction
     * @return void
     */
    public function beginTransaction() {
        $this->transaction->beginTransaction();
    }
    /**
     * commits the current transaction
     * if lockmode is optimistic this method starts a transaction
     * and commits it instantly
     *
     * @return void
     */
    public function commit() {
        $this->transaction->commit();
    }
    /**
     * rollback
     * rolls back all transactions
     *
     * this method also listens to onPreTransactionRollback and onTransactionRollback
     * eventlisteners
     *
     * @return void
     */
    public function rollback() {
        $this->transaction->rollback();
    }
    /**
     * saves the given record
     *
     * @param Doctrine_Record $record
     * @return void
     */
    public function save(Doctrine_Record $record) {
        $record->getTable()->getAttribute(Doctrine::ATTR_LISTENER)->onPreSave($record);


        switch($record->getState()):
            case Doctrine_Record::STATE_TDIRTY:
                $this->transaction->insert($record);
            break;
            case Doctrine_Record::STATE_DIRTY:
            case Doctrine_Record::STATE_PROXY:
                $this->transaction->update($record);
            break;
            case Doctrine_Record::STATE_CLEAN:
            case Doctrine_Record::STATE_TCLEAN:
                // do nothing
            break;
        endswitch;

        $record->getTable()->getAttribute(Doctrine::ATTR_LISTENER)->onSave($record);
    }
    /**
     * deletes this data access object and all the related composites
     * this operation is isolated by a transaction
     * 
     * this event can be listened by the onPreDelete and onDelete listeners
     *
     * @return boolean      true on success, false on failure
     */
    public function delete(Doctrine_Record $record) {
        if( ! $record->exists())
            return false;

        $this->beginTransaction();

        $record->getTable()->getListener()->onPreDelete($record);

        $this->unitOfWork->deleteComposites($record);

        $this->transaction->addDelete($record);

        $record->getTable()->getListener()->onDelete($record);

        $this->commit();
        return true;
    }
    /**
     * returns a string representation of this object
     * @return string
     */
    public function __toString() {
        return Doctrine_Lib::getConnectionAsString($this);
    }
}

