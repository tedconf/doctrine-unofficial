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

namespace Doctrine\ORM\Query\AST;

/**
 * DeleteStatement = DeleteClause [WhereClause]
 *
 * @author      Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        http://www.phpdoctrine.org
 * @since       2.0
 * @version     $Revision$
 */
class DeleteStatement extends Node
{
    private $_deleteClause;
    private $_whereClause;

    public function __construct($deleteClause)
    {
        $this->_deleteClause = $deleteClause;
    }
    
    public function setWhereClause($whereClause)
    {
        $this->_whereClause = $whereClause;
    }
    
    public function getDeleteClause()
    {
        return $this->_deleteClause;
    }

    public function getWhereClause()
    {
        return $this->_whereClause;
    }

    public function dispatch($sqlWalker)
    {
        return $sqlWalker->walkDeleteStatement($this);
    }
}