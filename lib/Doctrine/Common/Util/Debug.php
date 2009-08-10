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

namespace Doctrine\Common\Util;

/**
 * Static class containing most used debug methods.
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision: 3938 $
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  Giorgio Sironi <piccoloprincipeazzurro@gmail.com>
 */
final class Debug
{
    /**
     * Private constructor (prevents from instantiation)
     *
     */
    private function __construct() {}

    /**
     * Prints a dump of the public, protected and private properties of $var.
     * To print a meaningful dump, whose depth is limited, requires xdebug 
     * php extension.
     *
     * @static
     * @link http://xdebug.org/
     * @param mixed $var
     * @param integer $maxDepth Maximum nesting level for object properties
     */
    public static function dump($var, $maxDepth = 2)
    {
        ini_set('html_errors', 'On');
        ini_set('xdebug.var_display_max_depth', $maxDepth);
        
        ob_start();
        var_dump($var);
        $dump = ob_get_contents();
        ob_end_clean();
        
        echo strip_tags(html_entity_decode($dump));
        
        ini_set('html_errors', 'Off');
    }
}
