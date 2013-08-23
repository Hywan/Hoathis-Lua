<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2013, Ivan Enderlin. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace {

from('Hoathis')

/**
 * \Hoathis\Lua\Exception\Model
 */
-> import('Lua.Exception.Model')

/**
 * \Hoathis\Lua\Model\Environment
 */
-> import('Lua.Model.Environment');

}

namespace Hoathis\Lua\Model {

/**
 * Class \Hoathis\Lua\Model\Closure.
 *
 * Closure.
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2013 Ivan Enderlin.
 * @license    New BSD License
 */

class Closure extends Environment {

    protected $_body       = null;
    protected $_parameters = array();



    public function __construct ( $name, parent $parent,
                                  Array $parameters,
                                  $body ) {

        parent::__construct($name, $parent);

        foreach($parameters as $parameter) {

            $this->localSet($parameter, new Variable($parameter, $this));
            $this->_parameters[$parameter] = &$this[$parameter];
        }

        $this->_body = $body;

        return;
    }

    public function call ( Array $arguments, \Hoa\Visitor\Visit $interpreter ) {
    	if ($this->getBody() instanceof \Hoa\Compiler\Llk\TreeNode) {

			foreach($this->_parameters as $parameter) {

				$argument = current($arguments);

				if(false === $argument) {
                    $parameter->setValue(new Value(null));
                } else {
    				$parameter->setValue($argument);
                }

				next($arguments);
			}

			$out = $this->getBody()->accept($interpreter);
			foreach($this->_parameters as $parameter)
				$parameter->setValue(new Value(null));
			return $out;
		} elseif (true === is_callable($this->_body)) {
            $argValues = array();
            foreach ($arguments as $arg) {
                $argValues[] = $arg->getPHPValue();
            }
			call_user_func_array($this->_body, $argValues);
		} else {
			throw new \Hoathis\Lua\Exception\Interpreter('Invalid function body', 43, $this->_name);
		}
    }

    public function getBody ( ) {

        return $this->_body;
    }
}

}
