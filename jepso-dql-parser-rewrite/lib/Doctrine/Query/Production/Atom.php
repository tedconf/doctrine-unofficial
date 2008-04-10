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

/**
 * Atom = string | integer | float | input_parameter
 *
 * @package     Doctrine
 * @subpackage  Query
 * @author      Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author      Janne Vanhala <jpvanhal@cc.hut.fi>
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        http://www.phpdoctrine.org
 * @since       1.0
 * @version     $Revision$
 */
class Doctrine_Query_Production_Atom extends Doctrine_Query_Production
{
    protected $_type;

    protected $_value;


    public function syntax($paramHolder)
    {
        // Atom = string | integer | float | input_parameter
        switch ($this->_parser->lookahead['type']) {
            case Doctrine_Query_Token::T_STRING:
                $this->_parser->match(Doctrine_Query_Token::T_STRING);
                $this->_type = 'string';
            break;

            case Doctrine_Query_Token::T_INTEGER:
                $this->_parser->match(Doctrine_Query_Token::T_INTEGER);
                $this->_type = 'integer';
            break;

            case Doctrine_Query_Token::T_FLOAT:
                $this->_parser->match(Doctrine_Query_Token::T_FLOAT);
                $this->_type = 'float';
            break;

            case Doctrine_Query_Token::T_INPUT_PARAMETER:
                $this->_parser->match(Doctrine_Query_Token::T_INPUT_PARAMETER);
                $this->_type = 'param';
            break;

            default:
                $this->_parser->syntaxError('string, number or parameter (? or :)');
            break;
        }

        $this->_value = $this->_parser->token['value'];
    }


    public function buildSql()
    {
        switch ($this->_type) {
            case 'param':
                return $this->_value;
            break;

            case 'integer':
            case 'float':
                return $this->_parser->getSqlBuilder()->getConnection()->quote($this->_value, $this->_type);
            break;

            default:
                $conn = $this->_parser->getSqlBuilder()->getConnection();

                return $conn->string_quoting['start'] 
                     . $this->_parser->getConnection()->quote($this->_value, $this->_type)
                     . $conn->string_quoting['end'];
            break;
        }
    }
}
