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
Doctrine::autoload("Doctrine_Connection_Common");
/**
 * Doctrine_Connection_Sqlite
 *
 * @package     Doctrine
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @author      Konsta Vesterinen <kvesteri@cc.hut.fi>
 * @author      Lukas Smith <smith@pooteeweet.org> (PEAR MDB2 library)
 * @version     $Revision$
 * @category    Object Relational Mapping
 * @link        www.phpdoctrine.com
 * @since       1.0
 */
class Doctrine_Connection_Sqlite extends Doctrine_Connection_Common { 
    /**
     * @var string $driverName                  the name of this connection driver
     */
    protected $driverName = 'Sqlite';
    /**
     * the constructor
     *
     * @param Doctrine_Manager $manager
     * @param PDO $pdo                          database handle
     */
    public function __construct(Doctrine_Manager $manager, $adapter) {
        
        $this->supported = array(
                          'sequences'            => 'emulated',
                          'indexes'              => true,
                          'affected_rows'        => true,
                          'summary_functions'    => true,
                          'order_by_text'        => true,
                          'current_id'           => 'emulated',
                          'limit_queries'        => true,
                          'LOBs'                 => true,
                          'replace'              => true,
                          'transactions'         => true,
                          'savepoints'           => false,
                          'sub_selects'          => true,
                          'auto_increment'       => true,
                          'primary_key'          => true,
                          'result_introspection' => false, // not implemented
                          'prepared_statements'  => 'emulated',
                          'identifier_quoting'   => true,
                          'pattern_escaping'     => false,
                          );

        $this->options['base_transaction_name'] = '___php_MDB2_sqlite_auto_commit_off';
        $this->options['fixed_float'] = 0;
        $this->options['database_path'] = '';
        $this->options['database_extension'] = '';
        $this->options['server_version'] = '';

        parent::__construct($manager, $adapter);
    }
    /**
     * initializes database functions missing in sqlite
     */
    public function initFunctions() {
        $this->dbh->sqliteCreateFunction('md5',    array('Doctrine_Expression_Sqlite', 'md5Impl'), 1);
        $this->dbh->sqliteCreateFunction('mod',    array('Doctrine_Expression_Sqlite', 'modImpl'), 2);
        $this->dbh->sqliteCreateFunction('concat', array('Doctrine_Expression_Sqlite', 'concatImpl'));
        $this->dbh->sqliteCreateFunction('now', 'time', 0);
    }
    /**
     * Returns the current id of a sequence
     *
     * @param string $seq_name  name of the sequence
     * @return integer          the current id in the given sequence
     */
    public function currId($sequence) {
        $sequence = $this->quoteIdentifier($sequence, true);
        $seqColumn = $this->quoteIdentifier($this->options['seqcol_name'], true);
        $stmt = $this->dbh->query('SELECT MAX(' . $seqColumn . ') FROM ' . $sequence);
        $data = $stmt->fetch(PDO::FETCH_NUM);
        return $data[0];
    }
}

