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
-> import('Lua.Exception.Model');

}

namespace Hoathis\Lua\Model {

/**
 * Class \Hoathis\Lua\Model\Environment.
 *
 * Environment.
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2013 Ivan Enderlin.
 * @license    New BSD License
 */

class Environment implements \ArrayAccess {

    protected $_name         = null;
    protected $_environments = null;
    protected $_parent       = null;
    protected $_symbols      = array();


    public function __construct ( $name, self $parent = null ) {

        $this->_name         = $name;
        $this->_environments = new \SplStack();
        $this->_parent       = $parent;

        return;
    }

    public function offsetExists ( $symbol ) {
        $foundInLocal = array_key_exists($symbol, $this->_symbols);
        if (false === $foundInLocal && $this->_parent instanceof Environment) {
            return $this->_parent->offsetExists($symbol);
        }
        return $foundInLocal;
    }

    public function offsetGet ( $symbol ) {
        if (true === array_key_exists($symbol, $this->_symbols)) {
            return $this->_symbols[$symbol];
        } elseif ($this->_parent instanceof Environment) {
            return $this->_parent->offsetGet($symbol);
        } else {
            $var = new Variable($symbol, $this, new Value(null));
            $var->setValue(new Value(null));
            return $var;
        }
    }

    public function localExists($symbol) {
        return array_key_exists($symbol, $this->_symbols);
    }

    public function localSet($symbol, $value) {
        $this->_symbols[$symbol] = $value;
    }


    public function offsetSet ( $symbol, $value ) {
        if (false === array_key_exists($symbol, $this->_symbols)
                && $this->_parent instanceof Environment) {     // variables are global by default
            $this->_parent->offsetSet($symbol, $value);
        } else {
            $this->_symbols[$symbol] = $value;      // we are in global environment or the local symbol is declared
        }
        return $this;
    }

    public function offsetUnset ( $symbol ) {
        if (array_key_exists($symbol, $this->_symbols)) {
            unset($this->_symbols[$symbol]);
        } elseif ($this->_parent instanceof Environment) {
            $this->_parent->offsetUnset($symbol, $value);
        }

        return;
    }

    public function getName ( ) {

        return $this->_name;
    }

    public function getParent ( ) {

        return $this->_parent;
    }

    public function getSymbols() {
        return $this->_symbols;
    }

    public function getDeclaredSymbols() {
        return array_keys($this->_symbols);
    }
}

}
