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
 * SimpleConditionalExpression =
 *     Expression (ComparisonExpression | BetweenExpression | LikeExpression |
 *     InExpression | NullComparisonExpression | QuantifiedExpression) |
 *     ExistsExpression
 *
 * @package     Doctrine
 * @subpackage  Query
 * @author      Janne Vanhala <jpvanhal@cc.hut.fi>
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        http://www.phpdoctrine.org
 * @since       1.0
 * @version     $Revision$
 */
class Doctrine_Query_Production_SimpleConditionalExpression extends Doctrine_Query_Production
{
    protected function _getExpressionType() {
        if ($this->_isNextToken(Doctrine_Query_Token::T_NOT)) {
            $token = $this->_parser->getScanner()->peek();
            $this->_parser->getScanner()->resetPeek();
        } else {
            $token = $this->_parser->lookahead;
        }

        return $token['type'];
    }

    public function execute(array $params = array())
    {
        if ($this->_getExpressionType() === Doctrine_Query_Token::T_EXISTS) {
            $this->ExistsExpression();
        } else {
            $this->Expression();

            switch ($this->_getExpressionType()) {
                case Doctrine_Query_Token::T_BETWEEN:
                    $this->BetweenExpression();
                break;
                case Doctrine_Query_Token::T_LIKE:
                    $this->LikeExpression();
                break;
                case Doctrine_Query_Token::T_IN:
                    $this->InExpression();
                break;
                case Doctrine_Query_Token::T_IS:
                    $this->NullComparisonExpression();
                break;
                case Doctrine_Query_Token::T_NONE:
                    $this->ComparisonExpression();
                break;
                default:
                    $this->_parser->logError();
            }
        }

    }
}
