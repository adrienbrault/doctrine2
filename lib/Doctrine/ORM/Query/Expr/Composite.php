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

namespace Doctrine\ORM\Query\Expr;

/**
 * Expression class for building DQL and parts
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision$
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
class Composite extends Base
{
    public function add($arg)
    {
        if ( $arg !== null || ($arg instanceof Base && $arg->count() > 0) ) {
            // If we decide to keep Expr\Base instances, we can use this check

            $orderPriority = 0;
            if (is_array($arg) && isset($arg[1])) {
                $orderPriority = (int) $arg[1];
                $arg = $arg[0];
            }

            if ( ! is_string($arg)) {
                $class = get_class($arg);

                if ( ! in_array($class, $this->_allowedClasses)) {
                    throw new \InvalidArgumentException("Expression of type '$class' not allowed in this context.");
                }
            }

            $this->_parts[] = array(
                'part' => $arg,
                'order_priority' => $orderPriority,
            );
        }

        return $this;
    }

    public function __toString()
    {
        if ($this->count() === 1) {
            return (string) $this->_parts[0]['part'];
        }

        $sortedParts = $this->_parts;
        usort($sortedParts, function($row1, $row2) {
            return $row1['order_priority'] <= $row2['order_priority'] ? 1 : -1;
        });

        $components = array();

        foreach ($sortedParts as $part) {
            $components[] = $this->processQueryPart($part['part']);
        }

        return implode($this->_separator, $components);
    }


    private function processQueryPart($part)
    {
        $queryPart = (string) $part;

        if (is_object($part) && $part instanceof self && $part->count() > 1) {
            return $this->_preSeparator . $queryPart . $this->_postSeparator;
        }

        // Fixes DDC-1237: User may have added a where item containing nested expression (with "OR" or "AND")
        if (stripos($queryPart, ' OR ') !== false || stripos($queryPart, ' AND ') !== false) {
            return $this->_preSeparator . $queryPart . $this->_postSeparator;
        }

        return $queryPart;
    }
}